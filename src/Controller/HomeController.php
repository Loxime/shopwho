<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Service\RecommendationService;
use App\Service\TrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $products,
        CategoryRepository $categories,
        ReviewRepository $reviews,
        RecommendationService $recommendationService,
        TrackingService $tracking,
    ): Response {
        $query = trim(
            (string) $request->query->get('q', '')
        ) ?: null;

        $category = trim(
            (string) $request->query->get('category', '')
        ) ?: null;

        $tracking->track(
            'PAGE_VIEW',
            null,
            [
                'page' => 'catalog',
            ]
        );

        if ($query) {
            $tracking->track(
                'SEARCH',
                null,
                [
                    'query' => $query,
                ]
            );
        }

        if ($category) {
            $tracking->track(
                'CATEGORY_VIEW',
                null,
                [
                    'category' => $category,
                ]
            );
        }

        $catalogProducts = $products->findCatalog(
            $query,
            $category
        );

        $user = $this->getUser();

        if (!$user instanceof User) {
            $user = null;
        }

        $recommendations =
            $recommendationService->recommend(
                $user,
                8
            );

        $ratingProductIds = [];

        foreach ($catalogProducts as $product) {
            $productId = $product->getId();

            if ($productId !== null) {
                $ratingProductIds[$productId] = true;
            }
        }

        foreach ($recommendations as $recommendation) {
            $productId =
                $recommendation->product->getId();

            if ($productId !== null) {
                $ratingProductIds[$productId] = true;
            }
        }

        return $this->render(
            'home/index.html.twig',
            [
                'products' => $catalogProducts,
                'recommendations' =>
                    $recommendations,
                'productRatingStats' =>
                    $reviews
                        ->getRatingStatsByProductIds(
                            array_keys(
                                $ratingProductIds
                            )
                        ),
                'categories' =>
                    $categories->findBy(
                        [],
                        [
                            'name' => 'ASC',
                        ]
                    ),
                'query' => $query,
                'category' => $category,
            ]
        );
    }
}
