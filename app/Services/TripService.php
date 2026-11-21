<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
use App\Enums\TripStatus;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripService
{
    public function dispatch(array $data, User $user): Trip
    {
        return DB::transaction(function () use ($data, $user) {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($data['vehicle_id']);
            if ($vehicle->operational_status !== 'available') {
                throw ValidationException::withMessages(['vehicle_id' => 'The selected vehicle is not available.']);
            }

            $consignments = Consignment::query()->lockForUpdate()->whereIn('id', $data['consignment_ids'])->get();
            if ($consignments->count() !== count(array_unique($data['consignment_ids']))) {
                throw ValidationException::withMessages(['consignment_ids' => 'One or more consignments could not be found.']);
            }

            foreach ($consignments as $consignment) {
                $onActiveTrip = $consignment->trips()->whereIn('trips.status', [TripStatus::Loading->value, TripStatus::InTransit->value])->exists();
                if ($onActiveTrip || $consignment->status !== ConsignmentStatus::Received) {
                    throw ValidationException::withMessages([
                        'consignment_ids' => "{$consignment->tracking_number} is already on an active trip or is not ready to load.",
                    ]);
                }
            }

            $origin = Branch::query()->orderBy('route_order')->firstOrFail();
            $trip = Trip::create([
                'trip_number' => 'TRIP-'.now()->format('ymd-His'),
                'vehicle_id' => $vehicle->id,
                'driver_id' => $data['driver_id'],
                'origin_branch_id' => $origin->id,
                'current_branch_id' => $origin->id,
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

    public function advance(Trip $trip, User $user): Trip
    {
        if ($trip->status !== TripStatus::InTransit) {
            throw ValidationException::withMessages(['trip' => 'Only an active trip can advance.']);
        }

        return DB::transaction(function () use ($trip, $user) {
            $next = Branch::query()->where('route_order', '>', $trip->currentBranch->route_order)->orderBy('route_order')->first();
            if (! $next) {
                throw ValidationException::withMessages(['trip' => 'This trip is already at the final branch.']);
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
            $isFinalStop = ! Branch::where('route_order', '>', $next->route_order)->exists();
            if (! $hasCargo || $isFinalStop) {
                $trip->update(['status' => TripStatus::Completed, 'completed_at' => now()]);
            }

            return $trip->refresh()->load(['vehicle', 'driver', 'currentBranch', 'consignments.receiver', 'consignments.destinationBranch']);
        });
    }
}
