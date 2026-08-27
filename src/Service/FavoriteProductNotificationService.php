<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Product;
use App\Enum\NotificationType;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class FavoriteProductNotificationService
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly EntityManagerInterface $em
    ) {
    }

    public function notifyForProductUpdate(
        Product $product,
        int $previousPriceCents,
        int $previousStock,
        bool $previousIsActive
    ): int {
        $priceDropped =
            $product->isActive()
            && $product->getPriceCents()
                < $previousPriceCents;

        $backInStock =
            $previousStock <= 0
            && $product->getStock() > 0
            && $product->isActive();

        $reactivated =
            !$previousIsActive
            && $product->isActive()
            && $product->getStock() > 0;

        if (
            !$priceDropped
            && !$backInStock
            && !$reactivated
        ) {
            return 0;
        }

        $title = $this->buildTitle(
            $priceDropped,
            $backInStock || $reactivated
        );

        $message = $this->buildMessage(
            $product,
            $previousPriceCents,
            $priceDropped,
            $backInStock || $reactivated
        );

        $targetUrl =
            '/produit/'
            .$product->getSlug();

        $count = 0;

        foreach (
            $this->favorites
                ->findForProduct($product)
            as $favorite
        ) {
            $notification =
                new Notification(
                    $favorite->getUser(),
                    NotificationType::FavoriteProduct,
                    $title,
                    $message,
                    $targetUrl
                );

            $this->em->persist(
                $notification
            );

            ++$count;
        }

        return $count;
    }

    private function buildTitle(
        bool $priceDropped,
        bool $availableAgain
    ): string {
        if (
            $priceDropped
            && $availableAgain
        ) {
            return 'Bonne nouvelle pour un de vos favoris';
        }

        if ($priceDropped) {
            return 'Baisse de prix sur un favori';
        }

        return 'Un produit favori est de nouveau disponible';
    }

    private function buildMessage(
        Product $product,
        int $previousPriceCents,
        bool $priceDropped,
        bool $availableAgain
    ): string {
        $parts = [];

        if ($priceDropped) {
            $parts[] = sprintf(
                '%s passe de %s € à %s €.',
                $product->getName(),
                $this->formatPrice(
                    $previousPriceCents
                ),
                $this->formatPrice(
                    $product->getPriceCents()
                )
            );
        }

        if ($availableAgain) {
            $parts[] = sprintf(
                '%s est de nouveau disponible.',
                $product->getName()
            );
        }

        return implode(
            ' ',
            $parts
        );
    }

    private function formatPrice(
        int $priceCents
    ): string {
        return number_format(
            $priceCents / 100,
            2,
            ',',
            ' '
        );
    }
}
