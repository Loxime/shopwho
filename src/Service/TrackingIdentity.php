<?php

namespace App\Service;

final readonly class TrackingIdentity
{
    public function __construct(
        private string $visitorId,
        private string $sessionId,
    ) {
    }

    public function getVisitorId(): string
    {
        return $this->visitorId;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}
