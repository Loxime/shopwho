<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products): Response
    {
        $query = trim((string) $request->query->get('q', '')) ?: null;
        $category = trim((string) $request->query->get('category', '')) ?: null;

        return $this->render('home/index.html.twig', [
            'products' => $products->findCatalog($query, $category),
            'categories' => $products->findActiveCategories(),
            'query' => $query,
            'category' => $category,
        ]);
    }
}
