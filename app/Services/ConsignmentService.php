<?php

namespace App\Services;

use App\Enums\ConsignmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
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
        if ($amount < 1 || $amount > $consignment->balance) {
            throw ValidationException::withMessages(['amount' => 'The payment must be between KES 1 and the outstanding balance.']);
        }

        return DB::transaction(function () use ($consignment, $amount, $method, $user, $reference) {
            $consignment->payments()->create([
                'recorded_by' => $user->id,
                'amount' => $amount,
                'method' => $method,
                'status' => PaymentStatus::Completed,
                'reference' => $reference ?: Str::upper($method->value).'-'.$consignment->id.'-'.Str::upper(Str::random(5)),
                'paid_at' => now(),
            ]);
            $consignment->increment('paid_amount', $amount);

            return $consignment->refresh();
        });
    }

    public function deliver(Consignment $consignment, User $user): Consignment
    {
        if ($consignment->status !== ConsignmentStatus::Arrived) {
            throw ValidationException::withMessages(['consignment' => 'Only an arrived consignment can be handed over.']);
        }

        return DB::transaction(function () use ($consignment, $user) {
            if ($consignment->balance > 0) {
                $this->recordPayment($consignment, $consignment->balance, PaymentMethod::BalanceOnDelivery, $user);
            }

            $consignment->update(['status' => ConsignmentStatus::Delivered, 'delivered_at' => now()]);
            $consignment->trackingEvents()->create([
                'branch_id' => $consignment->destination_branch_id,
                'created_by' => $user->id,
                'status' => ConsignmentStatus::Delivered,
                'description' => 'Handed over to the receiver.',
                'occurred_at' => now(),
            ]);

            return $consignment->refresh();
        });
    }
}
