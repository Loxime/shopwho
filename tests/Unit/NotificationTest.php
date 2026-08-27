<?php

namespace App\Tests\Unit;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    public function testNotificationStartsUnreadAndNormalizesEmptyTargetUrl(): void
    {
        $notification = new Notification(
            new User(),
            NotificationType::System,
            'Information',
            'Message de test',
            '   '
        );

        self::assertFalse(
            $notification->isRead()
        );

        self::assertNull(
            $notification->getReadAt()
        );

        self::assertNull(
            $notification->getTargetUrl()
        );
    }

    public function testMarkAsReadIsIdempotent(): void
    {
        $notification = new Notification(
            new User(),
            NotificationType::System,
            'Information',
            'Message de test'
        );

        $firstReadAt = new \DateTimeImmutable(
            '2026-08-27 20:00:00'
        );

        $secondReadAt = new \DateTimeImmutable(
            '2026-08-27 21:00:00'
        );

        $notification->markAsRead(
            $firstReadAt
        );

        $notification->markAsRead(
            $secondReadAt
        );

        self::assertTrue(
            $notification->isRead()
        );

        self::assertSame(
            $firstReadAt,
            $notification->getReadAt()
        );
    }

    public function testNotificationCanBeMarkedUnreadAgain(): void
    {
        $notification = new Notification(
            new User(),
            NotificationType::FavoriteProduct,
            'Favori',
            'Message de test',
            '/produit/test'
        );

        $notification->markAsRead();

        self::assertTrue(
            $notification->isRead()
        );

        $notification->markAsUnread();

        self::assertFalse(
            $notification->isRead()
        );

        self::assertNull(
            $notification->getReadAt()
        );
    }
}
