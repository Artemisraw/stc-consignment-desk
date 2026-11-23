<?php

namespace App\Policies;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;

class TripPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Trip $trip): bool
    {
        return ! $user->role->isBranchScoped() || Trip::query()->visibleTo($user)->whereKey($trip->id)->exists();
    }

    public function create(User $user): bool { return $user->role->canDispatchTrips(); }

    /** Advance a trip. A branch agent may only record the truck reaching their own branch. */
    public function update(User $user, Trip $trip): bool
    {
        if (! $user->role->isBranchScoped()) {
            return true;
        }
        if ($trip->status !== TripStatus::InTransit) {
            return false;
        }

        return $user->worksAt(app(TripService::class)->nextStop($trip)?->id);
    }

    public function cancel(User $user, Trip $trip): bool { return $user->role->canDispatchTrips(); }
}
