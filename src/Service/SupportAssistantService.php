<?php

namespace App\Service;

final class SupportAssistantService
{
    /**
     * @return array{
     *     topic: string,
     *     message: string,
     *     actionLabel: ?string,
     *     actionRoute: ?string
     * }
     */
    public function reply(
        string $message
    ): array {
        $message = trim(
            mb_strtolower($message)
        );

        if ($message === '') {
            return $this->response(
                'welcome',
                'Bonjour ! Je peux vous aider concernant vos commandes, votre compte, vos favoris, vos avis ou le fonctionnement de Shopwho.'
            );
        }

        if ($this->containsAny(
            $message,
            [
                'paiement',
                'payer',
                'payé',
                'payee',
                'payée',
                'carte bancaire',
                'cb',
            ]
        )) {
            return $this->response(
                'payment',
                'Shopwho est une plateforme expérimentale. Les commandes réalisées depuis le panier sont simulées : aucun paiement réel n’est effectué.',
                'Voir mes commandes',
                'app_profile_orders'
            );
        }

        if ($this->containsAny(
            $message,
            [
                'commande',
                'commandes',
                'achat',
                'achats',
                'historique',
                'suivi',
            ]
        )) {
            return $this->response(
                'orders',
                'Vous pouvez retrouver l’historique et le détail de vos commandes depuis la rubrique « Mes commandes » de votre profil.',
                'Voir mes commandes',
                'app_profile_orders'
            );
        }

        if ($this->containsAny(
            $message,
            [
                'favori',
                'favoris',
                'favorite',
                'favorites',
                'préféré',
                'prefere',
            ]
        )) {
            return $this->response(
                'favorites',
                'Les produits ajoutés à vos favoris sont regroupés dans la rubrique « Mes favoris » de votre profil.',
                'Voir mes favoris',
                'app_profile_favorites'
            );
        }

        if ($this->containsAny(
            $message,
            [
                'avis',
                'note',
                'notation',
                'étoile',
                'etoile',
                'commentaire',
            ]
        )) {
            return $this->response(
                'reviews',
                'Vous pouvez consulter vos avis depuis votre profil. Un avis produit peut être publié après l’achat du produit concerné.',
                'Voir mes avis',
                'app_profile_reviews'
            );
        }

        if ($this->containsAny(
            $message,
            [
                'compte',
                'profil',
                'email',
                'e-mail',
                'mot de passe',
                'adresse',
                'livraison',
                'facturation',
            ]
        )) {
            return $this->response(
                'account',
                'Les informations de votre compte, vos coordonnées et vos adresses sont accessibles depuis votre profil.',
                'Ouvrir mon profil',
                'app_profile'
            );
        }

        if ($this->containsAny(
            $message,
            [
                'cookie',
                'cookies',
                'tracking',
                'suivi comportemental',
                'donnée',
                'donnee',
                'données',
                'donnees',
                'vie privée',
                'vie privee',
                'rgpd',
            ]
        )) {
            return $this->response(
                'privacy',
                'Shopwho utilise des données comportementales dans le cadre expérimental du projet. Le suivi comportemental dépend du consentement de suivi de l’utilisateur.'
            );
        }

        if ($this->containsAny(
            $message,
            [
                'produit',
                'produits',
                'catalogue',
                'recherche',
                'chercher',
                'catégorie',
                'categorie',
                'prix',
                'stock',
            ]
        )) {
            return $this->response(
                'catalog',
                'Le catalogue permet de rechercher des produits, de filtrer par catégorie, prix et disponibilité, puis de les trier selon plusieurs critères.',
                'Voir le catalogue',
                'app_home'
            );
        }

        return $this->response(
            'fallback',
            'Je n’ai pas trouvé de réponse précise. Je peux vous aider sur les commandes, les paiements simulés, le compte, les favoris, les avis, le catalogue ou les données de suivi.'
        );
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(
        string $message,
        array $needles
    ): bool {
        foreach ($needles as $needle) {
            if (str_contains(
                $message,
                $needle
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     topic: string,
     *     message: string,
     *     actionLabel: ?string,
     *     actionRoute: ?string
     * }
     */
    private function response(
        string $topic,
        string $message,
        ?string $actionLabel = null,
        ?string $actionRoute = null
    ): array {
        return [
            'topic' => $topic,
            'message' => $message,
            'actionLabel' => $actionLabel,
            'actionRoute' => $actionRoute,
        ];
    }
}
