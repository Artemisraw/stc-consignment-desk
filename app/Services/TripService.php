<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TripService
{
    private const ACTIVE_STATUSES = [TripStatus::Loading->value, TripStatus::InTransit->value];

    public function dispatch(array $data, User $user): Trip
    {
        return DB::transaction(function () use ($data, $user) {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($data['vehicle_id']);
            if ($vehicle->operational_status !== 'available') {
                throw ValidationException::withMessages(['vehicle_id' => 'The selected vehicle is not available.']);
            }

            $driver = Driver::query()->lockForUpdate()->findOrFail($data['driver_id']);
            $driverBusy = Trip::query()->where('driver_id', $driver->id)->whereIn('status', self::ACTIVE_STATUSES)->exists();
            if (! $driver->is_active || $driverBusy) {
                throw ValidationException::withMessages(['driver_id' => 'The selected driver is inactive or already on an active trip.']);
            }

            $consignments = Consignment::query()->with(['originBranch', 'destinationBranch'])
                ->lockForUpdate()->whereIn('id', $data['consignment_ids'])->get();
            if ($consignments->count() !== count(array_unique($data['consignment_ids']))) {
                throw ValidationException::withMessages(['consignment_ids' => 'One or more consignments could not be found.']);
            }

            foreach ($consignments as $consignment) {
                $onActiveTrip = $consignment->trips()->whereIn('trips.status', self::ACTIVE_STATUSES)->exists();
                if ($onActiveTrip || $consignment->status !== ConsignmentStatus::Received) {
                    throw ValidationException::withMessages([
                        'consignment_ids' => "{$consignment->tracking_number} is already on an active trip or is not ready to load.",
                    ]);
                }
            }

            // A trip runs one way along the corridor from a single loading branch.
            $origin = $consignments->first()->originBranch;
            $direction = TripDirection::between($origin->route_order, $consignments->first()->destinationBranch->route_order);
            foreach ($consignments as $consignment) {
                $sameRun = $consignment->origin_branch_id === $origin->id
                    && TripDirection::between($origin->route_order, $consignment->destinationBranch->route_order) === $direction;
                if (! $sameRun) {
                    throw ValidationException::withMessages([
                        'consignment_ids' => 'All consignments on a trip must load at the same branch and travel in the same direction.',
                    ]);
                }
            }

            $trip = Trip::create([
                'trip_number' => $this->newTripNumber(),
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'origin_branch_id' => $origin->id,
                'current_branch_id' => $origin->id,
                'direction' => $direction,
                'status' => TripStatus::InTransit,
                'departed_at' => now(),
            ]);

            $trip->consignments()->attach($consignments->pluck('id'));
            foreach ($consignments as $consignment) {
                $consignment->update(['status' => ConsignmentStatus::InTransit]);
                $consignment->trackingEvents()->create([
                    'branch_id' => $origin->id,
                    'created_by' => $user->id,
                    'status' => ConsignmentStatus::InTransit,
                    'description' => "Loaded on {$vehicle->registration_number} and departed.",
                    'metadata' => ['trip_number' => $trip->trip_number],
                    'occurred_at' => now(),
                ]);
            }

            return $trip->load(['vehicle', 'driver', 'currentBranch', 'consignments.receiver', 'consignments.destinationBranch']);
        });
    }

    /**
     * Record the truck reaching its next stop. Pass the stop the user is confirming so a double press,
     * two people pressing together, or a stale page cannot move the truck an extra stop.
     */
    public function advance(Trip $trip, User $user, ?int $confirmingStopId = null): Trip
    {
        return DB::transaction(function () use ($trip, $user, $confirmingStopId) {
            $trip = Trip::query()->lockForUpdate()->with('currentBranch')->findOrFail($trip->id);
            if ($confirmingStopId !== null && $trip->current_branch_id === $confirmingStopId) {
                throw ValidationException::withMessages(['trip' => "{$trip->trip_number} has already been recorded at {$trip->currentBranch->name}."]);
            }
            if ($trip->status !== TripStatus::InTransit) {
                throw ValidationException::withMessages(['trip' => 'Only an active trip can advance.']);
            }

            $next = $this->nextStop($trip);
            if (! $next) {
                throw ValidationException::withMessages(['trip' => 'This trip is already at the final branch.']);
            }
            if ($confirmingStopId !== null && $next->id !== $confirmingStopId) {
                throw ValidationException::withMessages([
                    'trip' => "{$trip->trip_number} is now at {$trip->currentBranch->name}. Refresh the page before recording an arrival.",
                ]);
            }
            // Re-checked under the lock: a branch agent records only the truck's arrival at their own branch.
            if (! $user->worksAt($next->id)) {
                throw ValidationException::withMessages(['trip' => "Only staff at {$next->name} can record this truck's arrival there."]);
            }

            $trip->update(['current_branch_id' => $next->id]);
            $trip->load('consignments.destinationBranch');

            foreach ($trip->consignments as $consignment) {
                if ($consignment->status !== ConsignmentStatus::InTransit) {
                    continue;
                }

                $arrived = $consignment->destination_branch_id === $next->id;
                if ($arrived) {
                    $consignment->update(['status' => ConsignmentStatus::Arrived]);
                    $trip->consignments()->updateExistingPivot($consignment->id, ['unloaded_at' => now()]);
                }
                $consignment->trackingEvents()->create([
                    'branch_id' => $next->id,
                    'created_by' => $user->id,
                    'status' => $arrived ? ConsignmentStatus::Arrived : ConsignmentStatus::InTransit,
                    'description' => $arrived ? "Arrived at {$next->name}; ready for collection." : "Passed through {$next->name}.",
                    'occurred_at' => now(),
                ]);
            }

            $hasCargo = $trip->consignments()->wherePivotNull('unloaded_at')->where('consignments.status', ConsignmentStatus::InTransit->value)->exists();
            $isFinalStop = ! $this->stopsAfter($next, $trip->direction)->exists();
            if (! $hasCargo || $isFinalStop) {
                $trip->update(['status' => TripStatus::Completed, 'completed_at' => now()]);
            }

            return $trip->refresh()->load(['vehicle', 'driver', 'currentBranch', 'consignments.receiver', 'consignments.destinationBranch']);
        });
    }

    /** Undo a dispatch made in error, before the truck reaches its first stop. */
    public function cancel(Trip $trip, User $user): Trip
    {
        return DB::transaction(function () use ($trip, $user) {
            $trip = Trip::query()->lockForUpdate()->with('originBranch')->findOrFail($trip->id);
            if ($trip->status !== TripStatus::InTransit || $trip->current_branch_id !== $trip->origin_branch_id) {
                throw ValidationException::withMessages(['trip' => 'Only a trip that has not reached its first stop can be cancelled.']);
            }

            $consignments = $trip->consignments()->lockForUpdate()->get();
            foreach ($consignments as $consignment) {
                if ($consignment->status !== ConsignmentStatus::InTransit) {
                    continue;
                }
                $consignment->update(['status' => ConsignmentStatus::Received]);
                $trip->consignments()->updateExistingPivot($consignment->id, ['unloaded_at' => now()]);
                $consignment->trackingEvents()->create([
                    'branch_id' => $trip->origin_branch_id,
                    'created_by' => $user->id,
                    'status' => ConsignmentStatus::Received,
                    'description' => "Trip cancelled; back at {$trip->originBranch->name} awaiting loading.",
                    'metadata' => ['trip_number' => $trip->trip_number],
                    'occurred_at' => now(),
                ]);
            }

            $trip->update(['status' => TripStatus::Cancelled, 'completed_at' => now()]);

            return $trip->refresh();
        });
    }

    /** The branch the trip reaches next, or null at the end of the route. */
    public function nextStop(Trip $trip): ?Branch
    {
        return $this->stopsAfter($trip->currentBranch, $trip->direction)->first();
    }

    /** Branches still ahead of the given branch, nearest first. */
    private function stopsAfter(Branch $branch, TripDirection $direction): Builder
    {
        return $direction === TripDirection::Outbound
            ? Branch::query()->where('route_order', '>', $branch->route_order)->orderBy('route_order')
            : Branch::query()->where('route_order', '<', $branch->route_order)->orderByDesc('route_order');
    }

    private function newTripNumber(): string
    {
        do {
            $number = 'TRIP-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Trip::where('trip_number', $number)->exists());

        return $number;
    }
}
