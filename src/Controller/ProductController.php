<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Form\ReviewType;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Repository\FavoriteRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Service\TrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/produit/{slug}', name: 'app_product_show', methods: ['GET'])]
public function show(
    #[MapEntity(mapping: ['slug' => 'slug'])]
    Product $product,
    TrackingService $tracking,
    ReviewRepository $reviews,
    OrderRepository $orders,
    FavoriteRepository $favorites,
): Response {
    if (!$product->isActive()) {
        throw $this->createNotFoundException();
    }

    $tracking->track('PRODUCT_VIEW', $product->getId(), [
        'category' => $product->getCategory()?->getSlug(),
        'price_cents' => $product->getPriceCents(),
    ]);

    $user = $this->getUser();
    $isFavorite =
        $user instanceof User
        && $favorites->isFavorite(
            $user,
            $product
        );
    $userReview = $user instanceof User ? $reviews->findOneByUserAndProduct($user, $product) : null;
    $canReview = $user instanceof User && !$userReview && $orders->hasUserPurchasedProduct($user, $product);
    $reviewForm = $canReview ? $this->createForm(ReviewType::class, new Review($user, $product, 5), [
        'action' => $this->generateUrl('app_profile_review_create', ['id' => $product->getId()]),
    ])->createView() : null;

    return $this->render('product/show.html.twig', [
        'product' => $product,
        'reviews' => $reviews->findForProduct($product),
        'ratingStats' => $reviews->getProductRatingStats($product),
        'userReview' => $userReview,
        'canReview' => $canReview,
        'reviewForm' => $reviewForm,
        'isFavorite' => $isFavorite,
    ]);
}
}
