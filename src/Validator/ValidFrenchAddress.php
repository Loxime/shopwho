<?php

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
final class ValidFrenchAddress extends Constraint
{
    public string $message = 'La ville « {{ city }} » ne correspond pas au code postal {{ postalCode }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
