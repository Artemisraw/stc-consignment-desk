<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = ['registration_number', 'make', 'model', 'capacity_kg', 'status', 'default_driver_id'];

    protected function casts(): array
    {
        return ['status' => VehicleStatus::class];
    }

    public function defaultDriver()
    {
        return $this->belongsTo(Driver::class, 'default_driver_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function getOperationalStatusAttribute(): string
    {
        return $this->trips()->whereIn('status', [TripStatus::Loading->value, TripStatus::InTransit->value])->exists()
            ? 'in_transit'
            : $this->status->value;
    }
}
