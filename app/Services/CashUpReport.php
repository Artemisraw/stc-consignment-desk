<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Money taken in and given back over a period, for the end-of-day cash-up.
 *
 * Received counts every payment taken in the period, including ones reversed later: the money
 * did come in that day. Reversals are counted on the day they were made, against the original
 * payment's branch, method and staff member, and are never netted out of the received figures.
 */
class CashUpReport
{
    public function build(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $received = $this->payments($branchId)
            ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Reversed->value])
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at')->orderBy('id')->get();

        $reversed = $this->payments($branchId)
            ->where('status', PaymentStatus::Reversed->value)
            ->whereBetween('reversed_at', [$start, $end])
            ->orderBy('reversed_at')->orderBy('id')->get();

        $methodOrder = array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::cases());

        return [
            'totals' => $this->totals($received, $reversed),
            'byMethod' => $this->summarise($received, $reversed, fn (Payment $p) => $p->method->value, fn (Payment $p) => $p->method->label())
                ->sortBy(fn (array $row) => array_search($row['key'], $methodOrder, true))->values(),
            'byBranch' => $this->summarise($received, $reversed, fn (Payment $p) => $p->branch_id ?? 0, fn (Payment $p) => $p->branch?->name ?? 'No branch')
                ->sortBy('label')->values(),
            'byStaff' => $this->summarise($received, $reversed, fn (Payment $p) => $p->recorded_by ?? 0, fn (Payment $p) => $p->recorder?->name ?? 'Unknown')
                ->sortBy('label')->values(),
            'payments' => $received->map(fn (Payment $p) => [
                'id' => $p->id,
                'paid_at' => $p->paid_at,
                'consignment_id' => $p->consignment_id,
                'tracking_number' => $p->consignment?->tracking_number,
                'method' => $p->method->label(),
                'reference' => $p->reference,
                'staff' => $p->recorder?->name,
                'branch' => $p->branch?->name,
                'amount' => $p->amount,
                'status' => $p->status->value,
            ])->values(),
            'reversals' => $reversed->map(fn (Payment $p) => [
                'id' => $p->id,
                'reversed_at' => $p->reversed_at,
                'paid_at' => $p->paid_at,
                'consignment_id' => $p->consignment_id,
                'tracking_number' => $p->consignment?->tracking_number,
                'method' => $p->method->label(),
                'reference' => $p->reference,
                'staff' => $p->recorder?->name,
                'reversed_by' => $p->reverser?->name,
                'reason' => $p->reversal_reason,
                'amount' => $p->amount,
            ])->values(),
        ];
    }

    private function payments(?int $branchId): Builder
    {
        return Payment::query()
            ->with(['consignment:id,tracking_number', 'recorder:id,name', 'reverser:id,name', 'branch:id,name'])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    private function totals(Collection $received, Collection $reversed): array
    {
        $in = (int) $received->sum('amount');
        $out = (int) $reversed->sum('amount');

        return ['payments' => $received->count(), 'received' => $in, 'reversals' => $reversed->count(), 'reversed' => $out, 'net' => $in - $out];
    }

    /** One row per group with received, reversed and net amounts. */
    private function summarise(Collection $received, Collection $reversed, callable $key, callable $label): Collection
    {
        return $received->concat($reversed)->groupBy($key)->map(function (Collection $group, $groupKey) use ($received, $reversed, $key, $label) {
            $in = $received->filter(fn (Payment $p) => $key($p) == $groupKey);
            $out = $reversed->filter(fn (Payment $p) => $key($p) == $groupKey);

            return ['key' => $groupKey, 'label' => $label($group->first())] + $this->totals($in, $out);
        })->values();
    }
}
