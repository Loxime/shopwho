<?php

namespace App\Service;

use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Enum\TrackingEventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class TrackingService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TrackingIdentityService $trackingIdentity,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function track(
        TrackingEventType $eventType,
        ?int $productId = null,
        array $metadata = []
    ): void {
        $this->trackBatch([
            [
                'eventType' => $eventType,
                'productId' => $productId,
                'metadata' => $metadata,
            ],
        ]);
    }

    /**
     * @param list<array{
     *     eventType: TrackingEventType|string,
     *     productId: ?int,
     *     metadata?: array<string, mixed>
     * }> $events
     */
    public function trackBatch(array $events): void
    {
        if ($events === []) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->hasSession()) {
            return;
        }

        $identity =
            $this->trackingIdentity->resolve(
                $request
            );

        if ($identity === null) {
            return;
        }

        $user = $this->security->getUser();

        foreach ($events as $eventData) {
            $eventType =
                $eventData['eventType'];

            if (is_string($eventType)) {
                $eventType =
                    TrackingEventType::tryFrom(
                        $eventType
                    );
            }

            if (
                !$eventType instanceof
                    TrackingEventType
            ) {
                throw new \InvalidArgumentException(
                    'Type d’événement de tracking inconnu.'
                );
            }

            $metadata = array_merge(
                [
                    'path' => $request->getPathInfo(),
                    'method' => $request->getMethod(),
                ],
                $eventData['metadata'] ?? []
            );

            $event = new TrackingEvent(
                $identity->getVisitorId(),
                $identity->getSessionId(),
                $eventType->value,
                $eventData['productId'],
                $metadata
            );

            if ($user instanceof User) {
                $event->setUser($user);
            }

            $this->entityManager->persist($event);
        }

        $this->entityManager->flush();
    }
}
