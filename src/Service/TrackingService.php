<?php

namespace App\Service;

use App\Entity\TrackingEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class TrackingService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function track(string $eventType, ?int $productId = null, array $metadata = []): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $visitorId = $session->get('tracking_visitor_id');
        if (!$visitorId) {
            $visitorId = bin2hex(random_bytes(16));
            $session->set('tracking_visitor_id', $visitorId);
        }

        $sessionId = $session->get('tracking_session_id');
        if (!$sessionId) {
            $sessionId = bin2hex(random_bytes(16));
            $session->set('tracking_session_id', $sessionId);
        }

        $metadata = array_merge([
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
        ], $metadata);

        $this->entityManager->persist(new TrackingEvent($visitorId, $sessionId, $eventType, $productId, $metadata));
        $this->entityManager->flush();
    }
}
