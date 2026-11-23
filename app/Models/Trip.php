<?php

namespace App\Models;

use App\Enums\TripDirection;
use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Builder;
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
    /**
     * Branch agents see trips that start at their branch or will reach it: a trip runs only as far
     * as its furthest cargo, so it reaches a branch when it carries cargo to that branch or beyond.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->role->isBranchScoped()) {
            return $query;
        }
        $order = $user->branch?->route_order;
        if ($order === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q
            ->where('trips.origin_branch_id', $user->branch_id)
            ->orWhere(fn (Builder $q) => $q->where('trips.direction', TripDirection::Outbound->value)
                ->whereHas('originBranch', fn (Builder $b) => $b->where('route_order', '<', $order))
                ->whereHas('consignments.destinationBranch', fn (Builder $b) => $b->where('route_order', '>=', $order)))
            ->orWhere(fn (Builder $q) => $q->where('trips.direction', TripDirection::Inbound->value)
                ->whereHas('originBranch', fn (Builder $b) => $b->where('route_order', '>', $order))
                ->whereHas('consignments.destinationBranch', fn (Builder $b) => $b->where('route_order', '<=', $order))));
    }

    public function consignments() { return $this->belongsToMany(Consignment::class, 'trip_consignments')->withPivot('unloaded_at'); }
}
