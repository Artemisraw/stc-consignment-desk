<?php

namespace App\Http\Controllers;

use App\Enums\ConsignmentStatus;
use App\Enums\TripStatus;
use App\Models\Consignment;
use App\Models\Trip;
use App\Models\Vehicle;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $vehicles = Vehicle::with('defaultDriver')->get()->map(fn (Vehicle $vehicle) => [
            'id' => $vehicle->id,
            'registration_number' => $vehicle->registration_number,
            'driver' => $vehicle->defaultDriver?->name,
            'status' => $vehicle->operational_status,
        ]);
        $consignments = Consignment::with(['receiver', 'destinationBranch'])->latest()->limit(8)->get()->map(fn (Consignment $c) => [
            'id' => $c->id,
            'tracking_number' => $c->tracking_number,
            'receiver' => $c->receiver->name,
            'destination' => $c->destinationBranch->name,
            'status' => $c->status->value,
            'total_amount' => $c->total_amount,
            'balance' => $c->balance,
        ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'vehicles' => $vehicles->count(),
                'in_transit' => $vehicles->where('status', 'in_transit')->count(),
                'available' => $vehicles->where('status', 'available')->count(),
                'maintenance' => $vehicles->where('status', 'maintenance')->count(),
                'waiting' => Consignment::where('status', ConsignmentStatus::Received->value)->count(),
                'outstanding' => (int) Consignment::query()->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS total')->value('total'),
            ],
            'vehicles' => $vehicles,
            'consignments' => $consignments,
            'activeTrips' => Trip::with(['vehicle', 'currentBranch'])->where('status', TripStatus::InTransit->value)->get(),
        ]);
    }
}
