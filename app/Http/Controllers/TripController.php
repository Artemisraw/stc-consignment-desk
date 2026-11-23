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

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Trip::class);
        $user = $request->user();
        $canDispatch = $user->can('create', Trip::class);
        $trips = Trip::with([
            'vehicle', 'driver', 'currentBranch',
            'consignments' => fn ($query) => $query->visibleTo($user)->with(['receiver', 'destinationBranch']),
        ])->visibleTo($user)->latest()->get()
            ->each(fn (Trip $trip) => $trip->setAttribute('can', [
                'advance' => $trip->status === TripStatus::InTransit && $user->can('update', $trip),
                'cancel' => $user->can('cancel', $trip),
            ]));

        return Inertia::render('Trips/Index', [
            'trips' => $trips,
            'vehicles' => $canDispatch ? Vehicle::with('defaultDriver')->get()->filter(fn (Vehicle $v) => $v->operational_status === 'available')->values() : [],
            'drivers' => $canDispatch ? Driver::where('is_active', true)->orderBy('name')->get() : [],
            'waiting' => $canDispatch ? Consignment::with(['receiver', 'originBranch', 'destinationBranch'])->where('status', ConsignmentStatus::Received->value)->get() : [],
            'branches' => Branch::orderBy('route_order')->get(),
            'canDispatch' => $canDispatch,
        ]);
    }

    public function manifest(Request $request, Trip $trip): Response
    {
        $this->authorize('view', $trip);
        // A branch agent's manifest lists only the cargo for their branch.
        $trip->load(['vehicle', 'driver', 'originBranch', 'currentBranch',
            'consignments' => fn ($query) => $query->visibleTo($request->user())->with(['receiver', 'destinationBranch', 'items'])]);

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

    public function cancel(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('cancel', $trip);
        $this->service->cancel($trip, $request->user());
        return back()->with('success', "{$trip->trip_number} cancelled; its consignments are waiting to load again.");
    }
}
