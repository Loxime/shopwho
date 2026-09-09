<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    #[Route(
        '/robots.txt',
        name: 'app_robots',
        methods: ['GET']
    )]
    public function robots(
        Request $request
    ): Response {
        $sitemapUrl =
            $request->getSchemeAndHttpHost()
            .$this->generateUrl(
                'app_sitemap'
            );

        $content = implode(
            "\n",
            [
                'User-agent: *',
                'Allow: /',
                'Disallow: /admin',
                'Disallow: /profil',
                'Disallow: /connexion',
                'Disallow: /inscription',
                'Disallow: /panier',
                'Disallow: /tracking',
                'Disallow: /support',
                '',
                'Sitemap: '.$sitemapUrl,
                '',
            ]
        );

        return new Response(
            $content,
            Response::HTTP_OK,
            [
                'Content-Type' =>
                    'text/plain; charset=UTF-8',
            ]
        );
    }

    #[Route(
        '/sitemap.xml',
        name: 'app_sitemap',
        methods: ['GET']
    )]
    public function sitemap(
        Request $request,
        ProductRepository $products,
        ArticleRepository $articles
    ): Response {
        $catalogProducts =
            $products->findCatalog();

        $categories = [];

        foreach ($catalogProducts as $product) {
            $category = $product->getCategory();

            if ($category === null) {
                continue;
            }

            $categories[
                $category->getSlug()
            ] = $category;
        }

        $response = new Response();

        $response->headers->set(
            'Content-Type',
            'application/xml; charset=UTF-8'
        );

        return $this->render(
            'seo/sitemap.xml.twig',
            [
                'baseUrl' =>
                    $request
                        ->getSchemeAndHttpHost(),
                'products' =>
                    $catalogProducts,
                'categories' =>
                    array_values(
                        $categories
                    ),
                'articles' =>
                    $articles->findPublished(),
            ],
            $response
        );
    }
}
