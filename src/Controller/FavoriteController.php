<?php

namespace App\Controller;

use App\Entity\Favorite;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Service\TrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profil/favoris')]
final class FavoriteController extends AbstractController
{
    #[Route(
        '',
        name: 'app_profile_favorites',
        methods: ['GET']
    )]
    public function index(
        FavoriteRepository $favorites
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render(
            'profile/favorites.html.twig',
            [
                'favorites' =>
                    $favorites->findForUser($user),
            ]
        );
    }

    #[Route(
        '/{id}/toggle',
        name: 'app_profile_favorite_toggle',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function toggle(
        Product $product,
        Request $request,
        FavoriteRepository $favorites,
        EntityManagerInterface $em,
        TrackingService $tracking
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (
            !$this->isCsrfTokenValid(
                'toggle-favorite-'.$product->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        $favorite =
            $favorites
                ->findOneForUserAndProduct(
                    $user,
                    $product
                );

        $source = (string) $request
            ->request
            ->get('source', 'product');

        if ($favorite instanceof Favorite) {
            $em->remove($favorite);
            $em->flush();

            $tracking->track(
                'FAVORITE_REMOVED',
                $product->getId(),
                [
                    'category' =>
                        $product
                            ->getCategory()
                            ?->getSlug(),
                    'source' => $source,
                ]
            );

            $this->addFlash(
                'success',
                'Produit retiré de vos favoris.'
            );
        } else {
            $favorite = new Favorite(
                $user,
                $product
            );

            $em->persist($favorite);
            $em->flush();

            $tracking->track(
                'FAVORITE_ADDED',
                $product->getId(),
                [
                    'category' =>
                        $product
                            ->getCategory()
                            ?->getSlug(),
                    'source' => $source,
                ]
            );

            $this->addFlash(
                'success',
                'Produit ajouté à vos favoris.'
            );
        }

        if (
            $request->request->get('redirect')
            === 'favorites'
        ) {
            return $this->redirectToRoute(
                'app_profile_favorites'
            );
        }

        return $this->redirectToRoute(
            'app_product_show',
            [
                'slug' => $product->getSlug(),
            ]
        );
    }
}
