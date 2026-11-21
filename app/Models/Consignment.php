<?php

namespace App\Models;

use App\Enums\ConsignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number', 'origin_branch_id', 'destination_branch_id', 'sender_id',
        'receiver_id', 'created_by', 'status', 'total_amount', 'paid_amount', 'notes', 'delivered_at',
    ];

    protected function casts(): array
    {
        return ['status' => ConsignmentStatus::class, 'delivered_at' => 'datetime'];
    }

    public function originBranch() { return $this->belongsTo(Branch::class, 'origin_branch_id'); }
    public function destinationBranch() { return $this->belongsTo(Branch::class, 'destination_branch_id'); }
    public function sender() { return $this->belongsTo(Customer::class, 'sender_id'); }
    public function receiver() { return $this->belongsTo(Customer::class, 'receiver_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function items() { return $this->hasMany(ConsignmentItem::class); }
    public function trackingEvents() { return $this->hasMany(TrackingEvent::class)->orderBy('occurred_at'); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function trips() { return $this->belongsToMany(Trip::class, 'trip_consignments')->withPivot('unloaded_at'); }

    public function getBalanceAttribute(): int
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }
}
