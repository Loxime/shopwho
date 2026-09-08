<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Order;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

final class OrderNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function simulatedOrderCreated(
        Order $order
    ): Notification {
        $notification = new Notification(
            $order->getUser(),
            NotificationType::Order,
            'Commande simulée enregistrée',
            sprintf(
                'Votre commande %s a bien été enregistrée. Aucun paiement réel n’a été effectué.',
                $order->getReference()
            ),
            '/profil/commandes/'
            .rawurlencode(
                $order->getReference()
            )
        );

        $this->entityManager->persist(
            $notification
        );

        return $notification;
    }
}
