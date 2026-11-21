<?php

namespace App\Models;

use App\Enums\ConsignmentStatus;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    protected $fillable = ['branch_id', 'created_by', 'status', 'description', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['status' => ConsignmentStatus::class, 'metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
}
