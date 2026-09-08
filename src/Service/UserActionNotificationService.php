<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

final class UserActionNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function profileUpdated(
        User $user
    ): Notification {
        return $this->create(
            $user,
            'Profil mis à jour',
            'Vos informations personnelles ont bien été enregistrées.',
            '/profil'
        );
    }

    public function shippingAddressSaved(
        User $user,
        bool $billingWasCopied = false
    ): Notification {
        if ($billingWasCopied) {
            return $this->create(
                $user,
                'Adresses enregistrées',
                'Vos adresses de livraison et de facturation ont bien été enregistrées.',
                '/profil/adresses'
            );
        }

        return $this->create(
            $user,
            'Adresse de livraison enregistrée',
            'Votre adresse de livraison a bien été enregistrée.',
            '/profil/adresses'
        );
    }

    public function billingAddressSaved(
        User $user
    ): Notification {
        return $this->create(
            $user,
            'Adresse de facturation enregistrée',
            'Votre adresse de facturation a bien été enregistrée.',
            '/profil/adresses'
        );
    }

    private function create(
        User $user,
        string $title,
        string $message,
        ?string $targetUrl = null
    ): Notification {
        $notification = new Notification(
            $user,
            NotificationType::System,
            $title,
            $message,
            $targetUrl
        );

        $this->entityManager->persist(
            $notification
        );

        return $notification;
    }
}
