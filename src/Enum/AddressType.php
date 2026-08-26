<?php

namespace App\Enum;

enum AddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';

    public function label(): string
    {
        return match ($this) {
            self::Shipping => 'Livraison',
            self::Billing => 'Facturation',
        };
    }
}
