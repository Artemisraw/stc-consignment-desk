<?php

namespace App\Http\Controllers;

use App\Enums\ConsignmentStatus;
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
            'waiting' => Consignment::with(['receiver', 'destinationBranch'])->where('status', ConsignmentStatus::Received->value)->get(),
            'branches' => Branch::orderBy('route_order')->get(),
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
