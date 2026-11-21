<?php

namespace App\Http\Controllers;

use App\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FleetController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Vehicle::class);
        return Inertia::render('Fleet/Index', [
            'vehicles' => Vehicle::with('defaultDriver')->get()->map(fn (Vehicle $v) => [
                ...$v->only('id', 'registration_number', 'make', 'model', 'capacity_kg'),
                'status' => $v->operational_status,
                'default_driver_id' => $v->default_driver_id,
                'driver' => $v->defaultDriver?->name,
            ]),
            'drivers' => Driver::orderBy('name')->get(),
            'canManage' => request()->user()->role->canManageFleet(),
        ]);
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $this->authorize('create', Vehicle::class);
        Vehicle::create($request->validate([
            'registration_number' => ['required', 'string', 'max:20', 'unique:vehicles'],
            'make' => ['nullable', 'string', 'max:50'], 'model' => ['nullable', 'string', 'max:50'],
            'capacity_kg' => ['nullable', 'integer', 'min:1'], 'default_driver_id' => ['nullable', 'exists:drivers,id'],
        ]));
        return back()->with('success', 'Vehicle added.');
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->canManageFleet(), 403);
        Driver::create($request->validate([
            'name' => ['required', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:30'],
            'licence_number' => ['required', 'string', 'max:50', 'unique:drivers'],
        ]));
        return back()->with('success', 'Driver added.');
    }

    public function toggleVehicle(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        abort_if($vehicle->operational_status === 'in_transit', 422, 'An active vehicle cannot be put in maintenance.');
        $vehicle->update(['status' => $vehicle->status === VehicleStatus::Available ? VehicleStatus::Maintenance : VehicleStatus::Available]);
        return back()->with('success', 'Vehicle status updated.');
    }
}
