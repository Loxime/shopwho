<?php

namespace App\Entity;

use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(
    repositoryClass: NotificationRepository::class
)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(
    name: 'idx_notification_user_read',
    columns: [
        'user_id',
        'read_at',
    ]
)]
#[ORM\Index(
    name: 'idx_notification_created_at',
    columns: ['created_at']
)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        inversedBy: 'notifications'
    )]
    #[ORM\JoinColumn(
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private User $user;

    #[ORM\Column(
        length: 40,
        enumType: NotificationType::class
    )]
    private NotificationType $type;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    private string $message;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Assert\Regex(
        pattern: '#^/[^\\s]*$#',
        message:
            'Le lien doit être un chemin interne.'
    )]
    private ?string $targetUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        NotificationType $type,
        string $title,
        string $message,
        ?string $targetUrl = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->user = $user;
        $this->type = $type;
        $this->title = trim($title);
        $this->message = trim($message);
        $this->targetUrl =
            $this->normalizeTargetUrl(
                $targetUrl
            );

        $this->createdAt =
            $createdAt
            ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function markAsRead(
        ?\DateTimeImmutable $readAt = null
    ): self {
        if ($this->readAt === null) {
            $this->readAt =
                $readAt
                ?? new \DateTimeImmutable();
        }

        return $this;
    }

    public function markAsUnread(): self
    {
        $this->readAt = null;

        return $this;
    }

    private function normalizeTargetUrl(
        ?string $targetUrl
    ): ?string {
        if ($targetUrl === null) {
            return null;
        }

        $targetUrl = trim(
            $targetUrl
        );

        return $targetUrl === ''
            ? null
            : $targetUrl;
    }
}
