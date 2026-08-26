<?php

namespace App\Controller;

use App\Repository\ReviewRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
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
        TrackingService $tracking,
    ): Response {
        $query = trim((string) $request->query->get('q', '')) ?: null;
        $category = trim((string) $request->query->get('category', '')) ?: null;

        $tracking->track('PAGE_VIEW', null, [
            'page' => 'catalog',
        ]);

        if ($query) {
            $tracking->track('SEARCH', null, [
                'query' => $query,
            ]);
        }

        if ($category) {
            $tracking->track('CATEGORY_VIEW', null, [
                'category' => $category,
            ]);
        }

        $catalogProducts = $products->findCatalog(
            $query,
            $category
        );

        $productIds = [];

        foreach ($catalogProducts as $product) {
            if ($product->getId() !== null) {
                $productIds[] = $product->getId();
            }
        }

        return $this->render('home/index.html.twig', [
            'products' => $catalogProducts,
            'productRatingStats' => $reviews
                ->getRatingStatsByProductIds($productIds),
            'categories' => $categories->findBy(
                [],
                ['name' => 'ASC']
            ),
            'query' => $query,
            'category' => $category,
        ]);
    }
}
