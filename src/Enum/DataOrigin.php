<?php

namespace App\Enum;

enum DataOrigin: string
{
    case Native = 'native';
    case Imported = 'imported';
}
