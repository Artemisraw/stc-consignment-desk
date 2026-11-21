<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['recorded_by', 'amount', 'method', 'status', 'reference', 'paid_at'];

    protected function casts(): array
    {
        return ['method' => PaymentMethod::class, 'status' => PaymentStatus::class, 'paid_at' => 'datetime'];
    }

    public function consignment() { return $this->belongsTo(Consignment::class); }
}
