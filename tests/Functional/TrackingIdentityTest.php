<?php

namespace App\Tests\Functional;

use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\KernelInterface;

class TrackingIdentityTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testConsentingAnonymousTrackingKeepsUserNull(): void
    {
        $client = static::createClient();
        $client->getCookieJar()->set(new Cookie('shopwho_tracking_consent', 'yes'));
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $event = $this->latestEvent();
        self::assertNull($event->getUser());
        self::assertNotSame('', $event->getVisitorId());
        self::assertNotSame('', $event->getSessionId());
    }

    public function testConsentingAuthenticatedTrackingStoresUserAndVisitorIdentity(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $client->getCookieJar()->set(new Cookie('shopwho_tracking_consent', 'yes'));
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $event = $this->latestEvent();
        self::assertSame($user->getId(), $event->getUser()?->getId());
        self::assertNotSame('', $event->getVisitorId());
        self::assertNotSame('', $event->getSessionId());
    }

    public function testAnonymousEventIsNotRetroactivelyAttachedAfterLogin(): void
    {
        $client = static::createClient();
        $client->getCookieJar()->set(new Cookie('shopwho_tracking_consent', 'yes'));
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $anonymousEvent = $this->latestEvent();
        $anonymousEventId = $anonymousEvent->getId();
        self::assertNull($anonymousEvent->getUser());

        $user = $this->createUser();
        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $authenticatedEvent = $em->getRepository(TrackingEvent::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(TrackingEvent::class, $authenticatedEvent);
        self::assertNotSame($anonymousEventId, $authenticatedEvent->getId());
        self::assertSame($user->getId(), $authenticatedEvent->getUser()?->getId());

        $persistedAnonymousEvent = $em->getRepository(TrackingEvent::class)->find($anonymousEventId);
        self::assertInstanceOf(TrackingEvent::class, $persistedAnonymousEvent);
        self::assertNull($persistedAnonymousEvent->getUser());
    }

    public function testRefusedTrackingCreatesNoEvent(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $before = $em->getRepository(TrackingEvent::class)->count([]);
        $client->getCookieJar()->set(new Cookie('shopwho_tracking_consent', 'no'));
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame($before, $em->getRepository(TrackingEvent::class)->count([]));
    }

    private function latestEvent(): TrackingEvent
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $event = $em->getRepository(TrackingEvent::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(TrackingEvent::class, $event);

        return $event;
    }

    private function createUser(): User
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $email = 'tracking-user-test@shopwho.local';
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
        }

        $user = (new User())->setEmail($email)->setPassword('not-used');
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
