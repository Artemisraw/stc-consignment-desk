<?php

namespace App\Http\Controllers;

use App\Enums\ConsignmentStatus;
use App\Enums\TripDirection;
use App\Enums\TripStatus;
use App\Http\Requests\StoreTripRequest;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\TripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function __construct(private readonly TripService $service) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Trip::class);
        return Inertia::render('Trips/Index', [
            'trips' => Trip::with(['vehicle', 'driver', 'currentBranch', 'consignments.receiver', 'consignments.destinationBranch'])
                ->latest()->get(),
            'vehicles' => Vehicle::with('defaultDriver')->get()->filter(fn (Vehicle $v) => $v->operational_status === 'available')->values(),
            'drivers' => Driver::where('is_active', true)->orderBy('name')->get(),
            'waiting' => Consignment::with(['receiver', 'originBranch', 'destinationBranch'])->where('status', ConsignmentStatus::Received->value)->get(),
            'branches' => Branch::orderBy('route_order')->get(),
        ]);
    }

    public function manifest(Trip $trip): Response
    {
        $this->authorize('viewAny', Trip::class);
        $trip->load(['vehicle', 'driver', 'originBranch', 'currentBranch', 'consignments.receiver', 'consignments.destinationBranch', 'consignments.items']);

        $rows = $trip->consignments
            ->sortBy(fn (Consignment $c) => $c->destinationBranch->route_order * ($trip->direction === TripDirection::Inbound ? -1 : 1))
            ->values()
            ->map(fn (Consignment $c) => [
                'tracking_number' => $c->tracking_number,
                'receiver' => $c->receiver->name,
                'phone' => $c->receiver->phone,
                'destination' => $c->destinationBranch->name,
                'pieces' => (int) $c->items->sum('quantity'),
                'paid' => $c->paid_amount,
                'balance' => $c->balance,
                'status' => $c->status->value,
            ]);

        return Inertia::render('Trips/Manifest', [
            'trip' => [
                'id' => $trip->id,
                'trip_number' => $trip->trip_number,
                'status' => $trip->status->value,
                'direction' => $trip->direction->value,
                'vehicle' => $trip->vehicle->registration_number,
                'driver' => $trip->driver->name,
                'driver_phone' => $trip->driver->phone,
                'driver_licence' => $trip->driver->licence_number,
                'origin' => $trip->originBranch->name,
                'current' => $trip->currentBranch->name,
                'departed_at' => $trip->departed_at,
            ],
            'rows' => $rows,
            'totals' => [
                'consignments' => $rows->count(),
                'pieces' => $rows->sum('pieces'),
                'paid' => $rows->sum('paid'),
                'balance' => $rows->sum('balance'),
            ],
            'printedAt' => now(),
        ]);
    }

    public function store(StoreTripRequest $request): RedirectResponse
    {
        $this->authorize('create', Trip::class);
        $trip = $this->service->dispatch($request->validated(), $request->user());
        return back()->with('success', "{$trip->trip_number} dispatched.");
    }

    public function advance(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);
        $this->service->advance($trip, $request->user());
        return back()->with('success', 'Trip advanced to the next stop.');
    }
}
