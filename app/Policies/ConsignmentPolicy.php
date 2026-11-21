<?php

namespace App\Policies;

use App\Models\Consignment;
use App\Models\User;

class ConsignmentPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Consignment $consignment): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Consignment $consignment): bool { return true; }
    public function deliver(User $user, Consignment $consignment): bool { return true; }
}
