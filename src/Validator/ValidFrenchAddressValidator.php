<?php

namespace App\Validator;

use App\Address\AddressLookupUnavailableException;
use App\Address\FrenchAddressLookup;
use App\Entity\Address;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidFrenchAddressValidator extends ConstraintValidator
{
    public function __construct(
        private readonly FrenchAddressLookup $addressLookup,
    ) {
    }

    public function validate(
        mixed $value,
        Constraint $constraint,
    ): void {
        if (!$constraint instanceof ValidFrenchAddress) {
            throw new UnexpectedTypeException(
                $constraint,
                ValidFrenchAddress::class
            );
        }

        if (!$value instanceof Address) {
            return;
        }

        if ($value->getCountryCode() !== 'FR') {
            return;
        }

        $line1 = trim($value->getLine1());
        $postalCode = trim($value->getPostalCode());
        $city = trim($value->getCity());

        /*
         * Les contraintes NotBlank existantes doivent rester responsables
         * des champs incomplets.
         */
        if (
            $line1 === ''
            || $postalCode === ''
            || $city === ''
        ) {
            return;
        }

        $query = sprintf(
            '%s %s %s',
            $line1,
            $postalCode,
            $city
        );

        try {
            $suggestions = $this->addressLookup->search(
                $query,
                5
            );
        } catch (AddressLookupUnavailableException) {
            /*
             * Une panne d'un service externe ne doit pas empêcher
             * l'utilisateur d'enregistrer son adresse.
             */
            return;
        }

        foreach ($suggestions as $suggestion) {
            if (
                $suggestion->postalCode === $postalCode
                && $this->normalize($suggestion->city)
                    === $this->normalize($city)
            ) {
                return;
            }
        }

        $this->context
            ->buildViolation($constraint->message)
            ->setParameter('{{ city }}', $city)
            ->setParameter(
                '{{ postalCode }}',
                $postalCode
            )
            ->atPath('city')
            ->addViolation();
    }

    private function normalize(string $value): string
    {
        return (new UnicodeString($value))
            ->ascii()
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                ''
            )
            ->toString();
    }
}
