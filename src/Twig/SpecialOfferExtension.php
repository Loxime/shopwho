<?php

namespace App\Twig;

use App\Repository\SpecialOfferRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SpecialOfferExtension extends AbstractExtension
{
    public function __construct(
        private readonly SpecialOfferRepository $offers
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'shopwho_header_offer',
                $this->offers->findActiveHeaderOffer(...)
            ),
        ];
    }
}
