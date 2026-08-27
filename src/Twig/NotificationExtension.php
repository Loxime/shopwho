<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRepository $notifications
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'shopwho_unread_notification_count',
                $this->unreadCount(...)
            ),
        ];
    }

    public function unreadCount(
        User $user
    ): int {
        return $this->notifications
            ->countUnreadForUser($user);
    }
}
