<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsignmentService
{
    public function create(array $data, User $user): Consignment
    {
        return DB::transaction(function () use ($data, $user) {
            $sender = empty($data['sender']['name']) ? null : Customer::create($data['sender']);
            $receiver = Customer::create($data['receiver']);
            $total = collect($data['items'])->sum(fn (array $item) => (int) $item['amount']);
            $paid = min((int) ($data['paid_amount'] ?? 0), $total);

            do {
                $trackingNumber = 'STC-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
            } while (Consignment::where('tracking_number', $trackingNumber)->exists());

            $consignment = Consignment::create([
                'tracking_number' => $trackingNumber,
                'origin_branch_id' => $data['origin_branch_id'],
                'destination_branch_id' => $data['destination_branch_id'],
                'sender_id' => $sender?->id,
                'receiver_id' => $receiver->id,
                'created_by' => $user->id,
                'status' => ConsignmentStatus::Received,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'notes' => $data['notes'] ?? null,
            ]);

            $consignment->items()->createMany($data['items']);
            $consignment->trackingEvents()->create([
                'branch_id' => $data['origin_branch_id'],
                'created_by' => $user->id,
                'status' => ConsignmentStatus::Received,
                'description' => 'Received at the consignment counter.',
                'occurred_at' => now(),
            ]);

            if ($paid > 0) {
                $consignment->payments()->create([
                    'recorded_by' => $user->id,
                    'branch_id' => $user->branch_id,
                    'amount' => $paid,
                    'method' => PaymentMethod::Cash,
                    'status' => PaymentStatus::Completed,
                    'reference' => 'CASH-'.$consignment->id.'-1',
                    'paid_at' => now(),
                ]);
            }

            return $consignment->load(['items', 'sender', 'receiver', 'originBranch', 'destinationBranch', 'trackingEvents.branch']);
        });
    }

    public function recordPayment(Consignment $consignment, int $amount, PaymentMethod $method, User $user, ?string $reference = null): Consignment
    {
        return DB::transaction(function () use ($consignment, $amount, $method, $user, $reference) {
            // Re-read under a row lock so concurrent submissions cannot both pass the balance check.
            $locked = Consignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if (in_array($locked->status, [ConsignmentStatus::Cancelled, ConsignmentStatus::Delivered], true)) {
                throw ValidationException::withMessages(['amount' => 'Payments can only be recorded on an open consignment.']);
            }
            if ($amount < 1 || $amount > $locked->balance) {
                throw ValidationException::withMessages(['amount' => 'The payment must be between KES 1 and the outstanding balance.']);
            }

            $locked->payments()->create([
                'recorded_by' => $user->id,
                'branch_id' => $user->branch_id,
                'amount' => $amount,
                'method' => $method,
                'status' => PaymentStatus::Completed,
                'reference' => $reference ?: Str::upper($method->value).'-'.$locked->id.'-'.Str::upper(Str::random(5)),
                'paid_at' => now(),
            ]);
            $locked->increment('paid_amount', $amount);

            return $consignment->refresh();
        });
    }

    public function deliver(Consignment $consignment, User $user): Consignment
    {
        return DB::transaction(function () use ($consignment, $user) {
            $locked = Consignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if ($locked->status !== ConsignmentStatus::Arrived) {
                throw ValidationException::withMessages(['consignment' => 'Only an arrived consignment can be handed over.']);
            }

            if ($locked->balance > 0) {
                $this->recordPayment($locked, $locked->balance, PaymentMethod::BalanceOnDelivery, $user);
            }

            $locked->update(['status' => ConsignmentStatus::Delivered, 'delivered_at' => now()]);
            $locked->trackingEvents()->create([
                'branch_id' => $locked->destination_branch_id,
                'created_by' => $user->id,
                'status' => ConsignmentStatus::Delivered,
                'description' => 'Handed over to the receiver.',
                'occurred_at' => now(),
            ]);

            return $consignment->refresh();
        });
    }

    /** Correct a consignment that has not been loaded yet. */
    public function update(Consignment $consignment, array $data, User $user): Consignment
    {
        return DB::transaction(function () use ($consignment, $data, $user) {
            $locked = Consignment::query()->with(['sender', 'receiver'])->lockForUpdate()->findOrFail($consignment->id);
            if ($locked->status !== ConsignmentStatus::Received) {
                throw ValidationException::withMessages(['consignment' => 'Only a consignment that has not been loaded can be edited.']);
            }

            $total = collect($data['items'])->sum(fn (array $item) => (int) $item['amount']);
            if ($total < $locked->paid_amount) {
                throw ValidationException::withMessages([
                    'items' => "The new total is below the KES {$locked->paid_amount} already paid. Reverse a payment first.",
                ]);
            }

            if (empty($data['sender']['name'])) {
                $senderId = null; // counter sale
            } elseif ($locked->sender) {
                $locked->sender->update($data['sender']);
                $senderId = $locked->sender->id;
            } else {
                $senderId = Customer::create($data['sender'])->id;
            }
            $locked->receiver->update($data['receiver']);

            $previousTotal = $locked->total_amount;
            $locked->update([
                'origin_branch_id' => $data['origin_branch_id'],
                'destination_branch_id' => $data['destination_branch_id'],
                'sender_id' => $senderId,
                'total_amount' => $total,
                'notes' => $data['notes'] ?? null,
            ]);
            $locked->items()->delete();
            $locked->items()->createMany($data['items']);

            $locked->trackingEvents()->create([
                'branch_id' => $locked->origin_branch_id,
                'created_by' => $user->id,
                'status' => ConsignmentStatus::Received,
                'description' => 'Consignment details corrected at the counter.',
                'metadata' => ['previous_total' => $previousTotal, 'total' => $total],
                'occurred_at' => now(),
            ]);

            return $consignment->refresh();
        });
    }

    public function cancel(Consignment $consignment, string $reason, User $user): Consignment
    {
        return DB::transaction(function () use ($consignment, $reason, $user) {
            $locked = Consignment::query()->lockForUpdate()->findOrFail($consignment->id);
            if ($locked->status !== ConsignmentStatus::Received) {
                throw ValidationException::withMessages(['consignment' => 'Only a consignment that has not been loaded can be cancelled.']);
            }
            if ($locked->paid_amount > 0) {
                throw ValidationException::withMessages([
                    'consignment' => "Reverse the KES {$locked->paid_amount} already paid before cancelling.",
                ]);
            }

            $locked->update([
                'status' => ConsignmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $locked->trackingEvents()->create([
                'branch_id' => $locked->origin_branch_id,
                'created_by' => $user->id,
                'status' => ConsignmentStatus::Cancelled,
                'description' => 'Consignment cancelled.',
                'metadata' => ['reason' => $reason],
                'occurred_at' => now(),
            ]);

            return $consignment->refresh();
        });
    }

    /** Reverse a completed payment. The row is kept and marked reversed; it is never deleted. */
    public function reversePayment(Consignment $consignment, Payment $payment, string $reason, User $user): Consignment
    {
        return DB::transaction(function () use ($consignment, $payment, $reason, $user) {
            $locked = Consignment::query()->lockForUpdate()->findOrFail($consignment->id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->consignment_id !== $locked->id) {
                throw ValidationException::withMessages(['payment' => 'This payment belongs to another consignment.']);
            }
            if ($lockedPayment->status !== PaymentStatus::Completed) {
                throw ValidationException::withMessages(['payment' => 'Only a completed payment can be reversed.']);
            }
            if (in_array($locked->status, [ConsignmentStatus::Delivered, ConsignmentStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['payment' => 'Payments on a delivered or cancelled consignment cannot be reversed.']);
            }

            $lockedPayment->update([
                'status' => PaymentStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $user->id,
                'reversal_reason' => $reason,
            ]);
            $locked->decrement('paid_amount', $lockedPayment->amount);

            return $consignment->refresh();
        });
    }
}
