<?php

namespace App\Enum;

enum SpecialOfferPlacement: string
{
    case Header = 'header';
    case Homepage = 'homepage';
    case Both = 'both';

    public function includesHeader(): bool
    {
        return in_array(
            $this,
            [
                self::Header,
                self::Both,
            ],
            true
        );
    }

    public function includesHomepage(): bool
    {
        return in_array(
            $this,
            [
                self::Homepage,
                self::Both,
            ],
            true
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Bannière du header',
            self::Homepage => 'Accueil',
            self::Both => 'Header et accueil',
        };
    }
}
