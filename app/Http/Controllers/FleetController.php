<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
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

    public function storeVehicle(StoreVehicleRequest $request): RedirectResponse
    {
        Vehicle::create($request->validated());

        return back()->with('success', 'Vehicle added.');
    }

    public function editVehicle(Vehicle $vehicle): Response
    {
        $this->authorize('update', $vehicle);

        return Inertia::render('Fleet/EditVehicle', [
            'vehicle' => $vehicle->only('id', 'registration_number', 'make', 'model', 'capacity_kg', 'default_driver_id'),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function updateVehicle(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        return redirect()->route('fleet.index')->with('success', 'Vehicle updated.');
    }

    public function storeDriver(StoreDriverRequest $request): RedirectResponse
    {
        Driver::create($request->validated());

        return back()->with('success', 'Driver added.');
    }

    public function editDriver(Driver $driver): Response
    {
        $this->authorize('update', $driver);

        return Inertia::render('Fleet/EditDriver', [
            'driver' => $driver->only('id', 'name', 'phone', 'licence_number', 'is_active'),
        ]);
    }

    public function updateDriver(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        $driver->update($request->validated());

        return redirect()->route('fleet.index')->with('success', 'Driver updated.');
    }

    public function toggleDriver(Driver $driver): RedirectResponse
    {
        $this->authorize('update', $driver);

        $hasActiveTrip = $driver->is_active && Trip::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', [TripStatus::Loading->value, TripStatus::InTransit->value])
            ->exists();

        if ($hasActiveTrip) {
            throw ValidationException::withMessages([
                'driver' => 'A driver on an active trip cannot be deactivated.',
            ]);
        }

        $driver->update(['is_active' => ! $driver->is_active]);

        return back()->with('success', $driver->is_active ? 'Driver activated.' : 'Driver deactivated.');
    }

    public function toggleVehicle(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('update', $vehicle);
        abort_if($vehicle->operational_status === 'in_transit', 422, 'An active vehicle cannot be put in maintenance.');

        $vehicle->update(['status' => $vehicle->status === VehicleStatus::Available ? VehicleStatus::Maintenance : VehicleStatus::Available]);

        return back()->with('success', 'Vehicle status updated.');
    }
}
