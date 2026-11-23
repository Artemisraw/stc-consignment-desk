<?php

namespace App\Models;

use App\Enums\TripDirection;
use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_number', 'vehicle_id', 'driver_id', 'origin_branch_id', 'current_branch_id', 'direction',
        'status', 'departed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['status' => TripStatus::class, 'direction' => TripDirection::class, 'departed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver() { return $this->belongsTo(Driver::class); }
    public function originBranch() { return $this->belongsTo(Branch::class, 'origin_branch_id'); }
    public function currentBranch() { return $this->belongsTo(Branch::class, 'current_branch_id'); }
    public function consignments() { return $this->belongsToMany(Consignment::class, 'trip_consignments')->withPivot('unloaded_at'); }
}
