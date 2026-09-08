<?php

namespace App\Tests\Functional;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class StorefrontFooterTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true
        );
    }

    public function testFooterDisplaysProjectInformation(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.footer .footer-grid'
        );

        self::assertSelectorTextContains(
            '.footer-brand',
            'SHOPWHO'
        );

        self::assertSelectorTextContains(
            '.footer-brand',
            'data science'
        );

        self::assertSelectorTextContains(
            '.footer',
            'aucun paiement réel'
        );
    }

    public function testGuestFooterDisplaysPublicNavigation(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorExists(
            '.footer a[href="/"]'
        );

        self::assertSelectorExists(
            '.footer a[href="/panier"]'
        );

        self::assertSelectorExists(
            '.footer a[href="/connexion"]'
        );

        self::assertSelectorExists(
            '.footer a[href="/inscription"]'
        );

        self::assertSelectorNotExists(
            '.footer a[href="/profil/commandes"]'
        );
    }

    public function testFooterDisplaysExperimentalDisclaimer(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.footer-bottom',
            'Projet expérimental'
        );

        self::assertSelectorTextContains(
            '.footer-bottom',
            'aucun achat réel'
        );
    }
}
