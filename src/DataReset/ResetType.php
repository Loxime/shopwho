<?php

namespace App\DataReset;

enum ResetType: string
{
    case Users = 'users';
    case Products = 'products';
}
