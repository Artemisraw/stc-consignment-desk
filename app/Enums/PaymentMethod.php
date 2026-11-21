<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BalanceOnDelivery = 'balance_on_delivery';
    case Mpesa = 'mpesa';
}
