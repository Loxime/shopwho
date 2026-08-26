<?php

namespace App\Service;

use App\Entity\TrackingEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class TrackingService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function track(
        string $eventType,
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
     *     eventType: string,
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

        if (
            $request->cookies->get(
                'shopwho_tracking_consent'
            ) !== 'yes'
        ) {
            return;
        }

        $session = $request->getSession();

        $visitorId = $session->get(
            'tracking_visitor_id'
        );

        if (!$visitorId) {
            $visitorId = bin2hex(random_bytes(16));

            $session->set(
                'tracking_visitor_id',
                $visitorId
            );
        }

        $sessionId = $session->get(
            'tracking_session_id'
        );

        if (!$sessionId) {
            $sessionId = bin2hex(random_bytes(16));

            $session->set(
                'tracking_session_id',
                $sessionId
            );
        }

        $user = $this->security->getUser();

        foreach ($events as $eventData) {
            $metadata = array_merge(
                [
                    'path' => $request->getPathInfo(),
                    'method' => $request->getMethod(),
                ],
                $eventData['metadata'] ?? []
            );

            $event = new TrackingEvent(
                $visitorId,
                $sessionId,
                $eventData['eventType'],
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
