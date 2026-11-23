<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function create(User $user): bool
    {
        return $user->role->canManageFleet();
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->role->canManageFleet();
    }
}
