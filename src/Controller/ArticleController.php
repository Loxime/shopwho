<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/articles')]
final class ArticleController extends AbstractController
{
    #[Route(
        '',
        name: 'app_article_index',
        methods: ['GET']
    )]
    public function index(
        ArticleRepository $articles
    ): Response {
        return $this->render(
            'article/index.html.twig',
            [
                'articles' =>
                    $articles->findPublished(),
            ]
        );
    }

    #[Route(
        '/{slug}',
        name: 'app_article_show',
        methods: ['GET']
    )]
    public function show(
        string $slug,
        ArticleRepository $articles
    ): Response {
        $article =
            $articles
                ->findPublishedBySlug(
                    $slug
                );

        if ($article === null) {
            throw $this
                ->createNotFoundException();
        }

        return $this->render(
            'article/show.html.twig',
            [
                'article' => $article,
            ]
        );
    }
}
