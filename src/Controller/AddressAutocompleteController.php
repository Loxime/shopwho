<?php

namespace App\Controller;

use App\Address\AddressLookupUnavailableException;
use App\Address\FrenchAddressLookup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AddressAutocompleteController extends AbstractController
{
    #[Route(
        '/profil/adresses/recherche',
        name: 'app_profile_address_autocomplete',
        methods: ['GET'],
    )]
    public function __invoke(
        Request $request,
        FrenchAddressLookup $addressLookup,
    ): JsonResponse {
        $query = trim(
            (string) $request->query->get('q', '')
        );

        if (strlen($query) < 3) {
            return $this->json([
                'available' => true,
                'suggestions' => [],
            ]);
        }

        try {
            $suggestions = $addressLookup->search(
                $query,
                5
            );
        } catch (AddressLookupUnavailableException) {
            return $this->json(
                [
                    'available' => false,
                    'suggestions' => [],
                ],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return $this->json([
            'available' => true,
            'suggestions' => array_map(
                static fn ($suggestion): array => [
                    'label' => $suggestion->label,
                    'line1' => $suggestion->name,
                    'postalCode' => $suggestion->postalCode,
                    'city' => $suggestion->city,
                ],
                $suggestions
            ),
        ]);
    }
}
