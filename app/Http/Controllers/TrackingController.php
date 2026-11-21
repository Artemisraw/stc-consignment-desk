<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Consignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tracking = trim((string) $request->query('tracking'));
        $consignment = null;
        if ($tracking !== '') {
            $model = Consignment::with(['receiver', 'originBranch', 'destinationBranch', 'trackingEvents.branch'])
                ->where('tracking_number', strtoupper($tracking))->first();
            if ($model) {
                $consignment = [
                    'tracking_number' => $model->tracking_number,
                    'receiver' => $model->receiver->name,
                    'origin' => $model->originBranch->name,
                    'destination' => $model->destinationBranch->name,
                    'status' => $model->status->value,
                    'total_amount' => $model->total_amount,
                    'paid_amount' => $model->paid_amount,
                    'balance' => $model->balance,
                    'events' => $model->trackingEvents,
                ];
            }
        }

        return Inertia::render('Tracking/Public', [
            'tracking' => $tracking,
            'searched' => $tracking !== '',
            'consignment' => $consignment,
            'branches' => Branch::orderBy('route_order')->get(['id', 'name', 'route_order']),
        ]);
    }
}
