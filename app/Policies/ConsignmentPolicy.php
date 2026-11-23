<?php

namespace App\Policies;

use App\Models\Consignment;
use App\Models\User;

/**
 * Clerks, managers and owners work across all branches. A branch agent works only with cargo
 * leaving from or going to their branch: corrections where it was booked, handover where it arrives.
 */
class ConsignmentPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Consignment $consignment): bool
    {
        return $user->worksAt($consignment->origin_branch_id) || $user->worksAt($consignment->destination_branch_id);
    }

    public function create(User $user): bool { return true; } // The origin branch is checked in StoreConsignmentRequest.

    /** Edit details at the counter that booked it. */
    public function update(User $user, Consignment $consignment): bool { return $user->worksAt($consignment->origin_branch_id); }

    public function cancel(User $user, Consignment $consignment): bool { return $user->worksAt($consignment->origin_branch_id); }

    /** Take money at either end of the journey. */
    public function recordPayment(User $user, Consignment $consignment): bool { return $this->view($user, $consignment); }

    /** Hand over only where the cargo is addressed. */
    public function deliver(User $user, Consignment $consignment): bool { return $user->worksAt($consignment->destination_branch_id); }

    public function reversePayment(User $user, Consignment $consignment): bool { return $user->role->canReversePayments(); }
}
