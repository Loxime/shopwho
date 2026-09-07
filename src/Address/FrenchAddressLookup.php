<?php

namespace App\Address;

use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class FrenchAddressLookup
{
    private const ENDPOINT = 'https://data.geopf.fr/geocodage/search';

    private const MIN_QUERY_LENGTH = 3;

    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 10;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<FrenchAddressSuggestion>
     */
    public function search(
        string $query,
        int $limit = self::DEFAULT_LIMIT,
    ): array {
        $query = trim($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $limit = max(
            1,
            min($limit, self::MAX_LIMIT)
        );

        try {
            $response = $this->httpClient->request(
                'GET',
                self::ENDPOINT,
                [
                    'query' => [
                        'q' => $query,
                        'limit' => $limit,
                    ],
                    'timeout' => 3.0,
                ]
            );

            $payload = $response->toArray();
        } catch (
            TransportExceptionInterface
            | HttpExceptionInterface
            | DecodingExceptionInterface $exception
        ) {
            throw new AddressLookupUnavailableException(
                'Le service de recherche d’adresse est indisponible.',
                previous: $exception,
            );
        }

        $features = $payload['features'] ?? null;

        if (!is_array($features)) {
            return [];
        }

        $suggestions = [];

        foreach ($features as $feature) {
            $suggestion = $this->mapFeature($feature);

            if ($suggestion !== null) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    /**
     * @param mixed $feature
     */
    private function mapFeature(
        mixed $feature
    ): ?FrenchAddressSuggestion {
        if (!is_array($feature)) {
            return null;
        }

        $properties = $feature['properties'] ?? null;

        if (!is_array($properties)) {
            return null;
        }

        $label = $properties['label'] ?? null;
        $name = $properties['name'] ?? null;
        $postalCode = $properties['postcode'] ?? null;
        $city = $properties['city'] ?? null;

        if (
            !is_string($label)
            || !is_string($name)
            || !is_string($postalCode)
            || !is_string($city)
        ) {
            return null;
        }

        $cityCode = $properties['citycode'] ?? '';
        $type = $properties['type'] ?? '';
        $score = $properties['score'] ?? 0.0;

        return new FrenchAddressSuggestion(
            label: $label,
            name: $name,
            postalCode: $postalCode,
            city: $city,
            cityCode: is_string($cityCode)
                ? $cityCode
                : '',
            type: is_string($type)
                ? $type
                : '',
            score: is_numeric($score)
                ? (float) $score
                : 0.0,
        );
    }
}
