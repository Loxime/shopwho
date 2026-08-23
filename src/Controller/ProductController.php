<?php

namespace App\Controller;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/produit/{slug}', name: 'app_product_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        if (!$product->isActive()) {
            throw $this->createNotFoundException();
        }

        return $this->render('product/show.html.twig', ['product' => $product]);
    }
}
