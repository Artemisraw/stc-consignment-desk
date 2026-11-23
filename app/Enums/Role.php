<?php

namespace App\Enums;

enum Role: string
{
    case Clerk = 'clerk';
    case BranchAgent = 'branch_agent';
    case Manager = 'manager';
    case Owner = 'owner';

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
