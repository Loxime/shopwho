<?php

namespace App\Enum;

enum NotificationType: string
{
    case FavoriteProduct = 'favorite_product';
    case Order = 'order';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::FavoriteProduct =>
                'Produit favori',
            self::Order =>
                'Commande',
            self::System =>
                'Information Shopwho',
        };
    }
}
