<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tracking_event')]
#[ORM\Index(columns: ['session_id', 'occurred_at'], name: 'idx_tracking_session_time')]
#[ORM\Index(columns: ['event_type', 'occurred_at'], name: 'idx_tracking_type_time')]
class TrackingEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $visitorId;

    #[ORM\Column(length: 64)]
    private string $sessionId;

    #[ORM\Column(length: 50)]
    private string $eventType;

    #[ORM\Column(nullable: true)]
    private ?int $productId = null;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(string $visitorId, string $sessionId, string $eventType, ?int $productId = null, array $metadata = [])
    {
        $this->visitorId = $visitorId;
        $this->sessionId = $sessionId;
        $this->eventType = $eventType;
        $this->productId = $productId;
        $this->metadata = $metadata;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVisitorId(): string { return $this->visitorId; }
    public function getSessionId(): string { return $this->sessionId; }
    public function getEventType(): string { return $this->eventType; }
    public function getProductId(): ?int { return $this->productId; }
    public function getMetadata(): array { return $this->metadata; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
