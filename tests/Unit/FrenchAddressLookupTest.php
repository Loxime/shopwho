<?php

namespace App\Tests\Unit;

use App\Address\AddressLookupUnavailableException;
use App\Address\FrenchAddressLookup;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FrenchAddressLookupTest extends TestCase
{
    public function testShortQueryDoesNotCallRemoteService(): void
    {
        $client = new MockHttpClient(
            static function (): MockResponse {
                self::fail(
                    'The HTTP client must not be called.'
                );
            }
        );

        $lookup = new FrenchAddressLookup($client);

        self::assertSame([], $lookup->search('Cl'));
    }

    public function testSearchMapsAddressSuggestions(): void
    {
        $client = new MockHttpClient(
            static function (
                string $method,
                string $url
            ): MockResponse {
                self::assertSame('GET', $method);

                $query = [];
                parse_str(
                    (string) parse_url(
                        $url,
                        PHP_URL_QUERY
                    ),
                    $query
                );

                self::assertSame(
                    '10 rue des Tests 63000 Clermont-Ferrand',
                    $query['q'] ?? null
                );

                self::assertSame(
                    '5',
                    (string) ($query['limit'] ?? '')
                );

                return new MockResponse(
                    json_encode(
                        [
                            'features' => [
                                [
                                    'properties' => [
                                        'label' => '10 Rue des Tests 63000 Clermont-Ferrand',
                                        'name' => '10 Rue des Tests',
                                        'postcode' => '63000',
                                        'city' => 'Clermont-Ferrand',
                                        'citycode' => '63113',
                                        'type' => 'housenumber',
                                        'score' => 0.97,
                                    ],
                                ],
                            ],
                        ],
                        JSON_THROW_ON_ERROR
                    ),
                    [
                        'http_code' => 200,
                        'response_headers' => [
                            'content-type: application/json',
                        ],
                    ]
                );
            }
        );

        $lookup = new FrenchAddressLookup($client);

        $suggestions = $lookup->search(
            '10 rue des Tests 63000 Clermont-Ferrand'
        );

        self::assertCount(1, $suggestions);

        $suggestion = $suggestions[0];

        self::assertSame(
            '10 Rue des Tests 63000 Clermont-Ferrand',
            $suggestion->label
        );
        self::assertSame(
            '10 Rue des Tests',
            $suggestion->name
        );
        self::assertSame(
            '63000',
            $suggestion->postalCode
        );
        self::assertSame(
            'Clermont-Ferrand',
            $suggestion->city
        );
        self::assertSame(
            '63113',
            $suggestion->cityCode
        );
        self::assertSame(
            'housenumber',
            $suggestion->type
        );
        self::assertSame(
            0.97,
            $suggestion->score
        );
    }

    public function testMalformedFeaturesAreIgnored(): void
    {
        $client = new MockHttpClient(
            new MockResponse(
                json_encode(
                    [
                        'features' => [
                            [
                                'properties' => [
                                    'label' => 'Incomplete result',
                                ],
                            ],
                            'invalid',
                        ],
                    ],
                    JSON_THROW_ON_ERROR
                ),
                [
                    'http_code' => 200,
                    'response_headers' => [
                        'content-type: application/json',
                    ],
                ]
            )
        );

        $lookup = new FrenchAddressLookup($client);

        self::assertSame(
            [],
            $lookup->search('Clermont-Ferrand')
        );
    }

    public function testRemoteFailureRaisesDomainException(): void
    {
        $client = new MockHttpClient(
            new MockResponse(
                'Service unavailable',
                [
                    'http_code' => 503,
                ]
            )
        );

        $lookup = new FrenchAddressLookup($client);

        $this->expectException(
            AddressLookupUnavailableException::class
        );

        $lookup->search('Clermont-Ferrand');
    }
}
