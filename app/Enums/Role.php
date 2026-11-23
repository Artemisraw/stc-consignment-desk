<?php

namespace App\Enums;

enum Role: string
{
    case Clerk = 'clerk';
    case BranchAgent = 'branch_agent';
    case Manager = 'manager';
    case Owner = 'owner';

    /** Branch agents work only with cargo to or from their own branch. Everyone else works across branches. */
    public function isBranchScoped(): bool
    {
        return $this === self::BranchAgent;
    }

    /** Loading and cancelling trips stays with clerks, managers and owners. */
    public function canDispatchTrips(): bool
    {
        return $this !== self::BranchAgent;
    }

    public function canManageFleet(): bool
    {
        return in_array($this, [self::Manager, self::Owner], true);
    }

    public function canReversePayments(): bool
    {
        return in_array($this, [self::Manager, self::Owner], true);
    }

    public function canViewReports(): bool
    {
        return in_array($this, [self::Manager, self::Owner], true);
    }
}
