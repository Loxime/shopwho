<?php

namespace App\Controller;

use App\Enum\TrackingEventType;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\PartnerRepository;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Service\RecommendationExperimentService;
use App\Repository\SpecialOfferRepository;
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
        ArticleRepository $articles,
        PartnerRepository $partners,
        ProductRepository $products,
        CategoryRepository $categories,
        ReviewRepository $reviews,
        RecommendationExperimentService $recommendationExperiment,
        TrackingService $tracking,
        SpecialOfferRepository $specialOffers,
    ): Response {
        $query = trim(
            (string) $request->query->get('q', '')
        ) ?: null;

        $category = trim(
            (string) $request->query->get('category', '')
        ) ?: null;

        $sort = trim(
            (string) $request->query->get(
                'sort',
                'newest'
            )
        );

        if (
            !in_array(
                $sort,
                [
                    'newest',
                    'price_asc',
                    'price_desc',
                    'name_asc',
                ],
                true
            )
        ) {
            $sort = 'newest';
        }

        $minPriceCents =
            $this->priceFilterToCents(
                $request->query->get(
                    'min_price'
                )
            );

        $maxPriceCents =
            $this->priceFilterToCents(
                $request->query->get(
                    'max_price'
                )
            );

        $inStockOnly =
            (string) $request->query->get(
                'in_stock',
                ''
            ) === '1';

        $hasCatalogFilters =
            $minPriceCents !== null
            || $maxPriceCents !== null
            || $inStockOnly;

        $showEditorialShowcase =
            $query === null
            && $category === null
            && !$hasCatalogFilters
            && $sort === 'newest';

        $tracking->track(
            TrackingEventType::PageView,
            null,
            [
                'page' => 'catalog',
            ]
        );

        if ($query) {
            $tracking->track(
                TrackingEventType::Search,
                null,
                [
                    'query' => $query,
                ]
            );
        }

        if ($category) {
            $tracking->track(
                TrackingEventType::CategoryView,
                null,
                [
                    'category' => $category,
                ]
            );
        }

        $catalogProducts = $products->findCatalog(
            $query,
            $category,
            $sort,
            $minPriceCents,
            $maxPriceCents,
            $inStockOnly
        );

        $user = $this->getUser();

        if (!$user instanceof User) {
            $user = null;
        }

        $recommendations =
            $recommendationExperiment->recommend(
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
        $homepageOffers =
            $specialOffers
                ->findActiveHomepageOffers(8);

        $latestArticles = [];
        $featuredPartners = [];

        if ($showEditorialShowcase) {
            $latestArticles =
                $articles->findPublished(3);

            $featuredPartners =
                array_slice(
                    $partners->findActive(),
                    0,
                    6
                );
        }

        return $this->render(
            'home/index.html.twig',
            [
                'products' => $catalogProducts,
                'recommendations' =>
                    $recommendations,
                'specialOffers' => $homepageOffers,
                'latestArticles' =>
                    $latestArticles,
                'featuredPartners' =>
                    $featuredPartners,
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
                'sort' => $sort,
                'minPriceCents' =>
                    $minPriceCents,
                'maxPriceCents' =>
                    $maxPriceCents,
                'inStockOnly' =>
                    $inStockOnly,
                'hasCatalogFilters' =>
                    $hasCatalogFilters,
            ]
        );
    }
    private function priceFilterToCents(
        mixed $value
    ): ?int {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        $value = str_replace(
            ',',
            '.',
            $value
        );

        $amount = filter_var(
            $value,
            FILTER_VALIDATE_FLOAT
        );

        if (
            $amount === false
            || $amount < 0
            || $amount
                > PHP_INT_MAX / 100
        ) {
            return null;
        }

        return (int) round(
            $amount * 100
        );
    }

}
