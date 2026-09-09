<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/articles')]
final class AdminArticleController extends AbstractController
{
    #[Route(
        '',
        name: 'admin_article_index',
        methods: ['GET']
    )]
    public function index(
        ArticleRepository $articles
    ): Response {
        return $this->render(
            'admin/article/index.html.twig',
            [
                'articles' =>
                    $articles->findBy(
                        [],
                        [
                            'updatedAt' => 'DESC',
                        ]
                    ),
            ]
        );
    }

    #[Route(
        '/new',
        name: 'admin_article_new',
        methods: ['GET', 'POST']
    )]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        return $this->handleForm(
            new Article(),
            $request,
            $em,
            $slugger,
            true
        );
    }

    #[Route(
        '/{id}/edit',
        name: 'admin_article_edit',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    public function edit(
        Article $article,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        return $this->handleForm(
            $article,
            $request,
            $em,
            $slugger,
            false
        );
    }

    #[Route(
        '/{id}/toggle',
        name: 'admin_article_toggle',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function toggle(
        Article $article,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'toggle-article-'
                    .$article->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_article_index'
            );
        }

        $published =
            !$article->isPublished();

        $article->setIsPublished(
            $published
        );

        if (
            $published
            && $article->getPublishedAt()
                === null
        ) {
            $article->setPublishedAt(
                new \DateTimeImmutable()
            );
        }

        $em->flush();

        $this->addFlash(
            'success',
            $published
                ? 'Article publié.'
                : 'Article repassé en brouillon.'
        );

        return $this->redirectToRoute(
            'admin_article_index'
        );
    }

    #[Route(
        '/{id}',
        name: 'admin_article_delete',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function delete(
        Article $article,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            $this->isCsrfTokenValid(
                'delete-article-'
                    .$article->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            $em->remove($article);
            $em->flush();

            $this->addFlash(
                'success',
                'Article supprimé.'
            );
        }

        return $this->redirectToRoute(
            'admin_article_index'
        );
    }

    private function handleForm(
        Article $article,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        bool $isNew
    ): Response {
        $form = $this->createForm(
            ArticleType::class,
            $article
        );

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $source =
                $article->getSlug() !== ''
                    ? $article->getSlug()
                    : $article->getTitle();

            $article->setSlug(
                strtolower(
                    $slugger
                        ->slug($source)
                        ->toString()
                )
            );

            if (
                $article->isPublished()
                && $article->getPublishedAt()
                    === null
            ) {
                $article->setPublishedAt(
                    new \DateTimeImmutable()
                );
            }
        }

        if (
            $form->isSubmitted()
            && $form->isValid()
        ) {
            if ($isNew) {
                $em->persist($article);
            }

            $em->flush();

            $this->addFlash(
                'success',
                $isNew
                    ? 'Article créé.'
                    : 'Article modifié.'
            );

            return $this->redirectToRoute(
                'admin_article_index'
            );
        }

        return $this->render(
            'admin/article/form.html.twig',
            [
                'form' => $form,
                'article' => $article,
                'title' => $isNew
                    ? 'Nouvel article'
                    : 'Modifier l’article',
            ]
        );
    }
}
