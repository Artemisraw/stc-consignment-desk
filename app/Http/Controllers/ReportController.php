<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Payment;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Reports/Index', [
            'summary' => [
                'revenue' => (int) Payment::where('status', 'completed')->sum('amount'),
                'outstanding' => (int) Consignment::where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS total')->value('total'),
                'consignments' => Consignment::count(),
                'delivered' => Consignment::where('status', 'delivered')->count(),
            ],
            'byDestination' => Consignment::query()->join('branches', 'branches.id', '=', 'consignments.destination_branch_id')
                ->where('consignments.status', '!=', 'cancelled')
                ->selectRaw('branches.name, COUNT(*) as consignments, SUM(consignments.total_amount) as value')
                ->groupBy('branches.id', 'branches.name')->orderByDesc('value')->get(),
        ]);
    }
}
