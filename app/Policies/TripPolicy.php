<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Trip $trip): bool { return true; }
    public function cancel(User $user, Trip $trip): bool { return true; }
}
