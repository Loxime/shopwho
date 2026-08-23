<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Form\ReviewType;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profil/avis')]
class ReviewController extends AbstractController
{
    #[Route('', name: 'app_profile_reviews', methods: ['GET'])]
    public function index(ReviewRepository $reviews): Response
    {
        return $this->render('review/index.html.twig', ['reviews' => $reviews->findForUser($this->currentUser())]);
    }

    #[Route('/produit/{id}', name: 'app_profile_review_create', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function create(Product $product, Request $request, ReviewRepository $reviews, OrderRepository $orders, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        if (!$product->isActive()) { throw $this->createNotFoundException(); }
        if ($reviews->findOneByUserAndProduct($user, $product)) {
            $this->addFlash('error', 'Vous avez déjà publié un avis sur ce produit.');
            return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
        }
        if (!$orders->hasUserPurchasedProduct($user, $product)) {
            $this->addFlash('error', 'Vous devez avoir acheté ce produit pour publier un avis.');
            return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
        }

        $review = new Review($user, $product, 5);
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($review);
            try {
                $em->flush();
                $this->addFlash('success', 'Votre avis a été publié.');
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Vous avez déjà publié un avis sur ce produit.');
            }
        } else {
            $this->addFlash('error', 'Votre avis n’a pas pu être publié. Vérifiez le formulaire.');
        }

        return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
    }

    #[Route('/{id}/modifier', name: 'app_profile_review_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertOwner($review);
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre avis a été modifié.');
            return $this->redirectToRoute('app_profile_reviews');
        }

        return $this->render('review/edit.html.twig', ['review' => $review, 'form' => $form]);
    }

    #[Route('/{id}/supprimer', name: 'app_profile_review_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertOwner($review);
        if ($this->isCsrfTokenValid('delete-review-'.$review->getId(), (string) $request->request->get('_token'))) {
            $em->remove($review);
            $em->flush();
            $this->addFlash('success', 'Votre avis a été supprimé.');
        } else {
            $this->addFlash('error', 'Le jeton de sécurité est invalide. L’avis a été conservé.');
        }
        return $this->redirectToRoute('app_profile_reviews');
    }

    private function assertOwner(Review $review): void
    {
        if ($review->getUser()->getId() !== $this->currentUser()->getId()) { throw $this->createNotFoundException(); }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) { throw $this->createAccessDeniedException(); }
        return $user;
    }
}
