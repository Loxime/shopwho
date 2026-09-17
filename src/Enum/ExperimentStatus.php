<?php

namespace App\Enum;

enum ExperimentStatus: string
{
    case Draft = 'draft';
    case Running = 'running';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Running => 'En cours',
            self::Paused => 'En pause',
            self::Ended => 'Terminée',
        };
    }
}
