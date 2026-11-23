<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashUpRequest;
use App\Models\Branch;
use App\Services\CashUpReport;
use Inertia\Inertia;
use Inertia\Response;

class CashUpController extends Controller
{
    public function __invoke(CashUpRequest $request, CashUpReport $report): Response
    {
        [$from, $to] = $request->period();
        $branchId = $request->branchId();

        return Inertia::render('Reports/CashUp', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'branch_id' => $branchId],
            'branches' => Branch::query()->orderBy('route_order')->get(['id', 'name']),
            'report' => $report->build($from, $to, $branchId),
            'preparedBy' => $request->user()->name,
            'printedAt' => now(),
        ]);
    }
}
