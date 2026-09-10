<?php

namespace App\Tests;

use App\Service\TrackingIdentityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class TrackingIdentityServiceTest extends TestCase
{
    public function testDoesNotCreateIdentityWithoutConsent(): void
    {
        $request = Request::create('/');

        $session = new Session(
            new MockArraySessionStorage()
        );

        $request->setSession($session);

        $service =
            new TrackingIdentityService();

        self::assertNull(
            $service->resolve($request)
        );

        self::assertFalse(
            $session->has(
                TrackingIdentityService::VISITOR_SESSION_KEY
            )
        );

        self::assertFalse(
            $session->has(
                TrackingIdentityService::SESSION_SESSION_KEY
            )
        );
    }

    public function testCreatesStableIdentityWithConsent(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [
                TrackingIdentityService::CONSENT_COOKIE
                    => 'yes',
            ]
        );

        $session = new Session(
            new MockArraySessionStorage()
        );

        $request->setSession($session);

        $service =
            new TrackingIdentityService();

        $first = $service->resolve($request);
        $second = $service->resolve($request);

        self::assertNotNull($first);
        self::assertNotNull($second);

        self::assertSame(
            $first->getVisitorId(),
            $second->getVisitorId()
        );

        self::assertSame(
            $first->getSessionId(),
            $second->getSessionId()
        );

        self::assertSame(
            32,
            strlen($first->getVisitorId())
        );

        self::assertSame(
            32,
            strlen($first->getSessionId())
        );
    }

    public function testKeepsExistingTrackingIdentity(): void
    {
        $request = Request::create(
            '/',
            'GET',
            [],
            [
                TrackingIdentityService::CONSENT_COOKIE
                    => 'yes',
            ]
        );

        $session = new Session(
            new MockArraySessionStorage()
        );

        $session->set(
            TrackingIdentityService::VISITOR_SESSION_KEY,
            'visitor-existing'
        );

        $session->set(
            TrackingIdentityService::SESSION_SESSION_KEY,
            'session-existing'
        );

        $request->setSession($session);

        $service =
            new TrackingIdentityService();

        $identity =
            $service->resolve($request);

        self::assertNotNull($identity);

        self::assertSame(
            'visitor-existing',
            $identity->getVisitorId()
        );

        self::assertSame(
            'session-existing',
            $identity->getSessionId()
        );
    }
}
