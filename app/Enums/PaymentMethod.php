<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BalanceOnDelivery = 'balance_on_delivery';
    case Mpesa = 'mpesa';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BalanceOnDelivery => 'Balance on delivery',
            self::Mpesa => 'M-Pesa',
        };
    }
}
