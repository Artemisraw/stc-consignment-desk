<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['recorded_by', 'branch_id', 'amount', 'method', 'status', 'reference', 'paid_at', 'reversed_at', 'reversed_by', 'reversal_reason'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'method' => PaymentMethod::class, 'status' => PaymentStatus::class, 'paid_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
}
