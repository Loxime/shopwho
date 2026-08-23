<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/products')]
class AdminProductController extends AbstractController
{
    #[Route('', name: 'admin_product_index', methods: ['GET'])]
    public function index(ProductRepository $repository): Response
    {
        return $this->render('admin/product/index.html.twig', [
            'products' => $repository->findBy([], ['updatedAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$product->getSlug()) {
                $product->setSlug(strtolower($slugger->slug($product->getName())->toString()));
            }
            $em->persist($product);
            $em->flush();
            $this->addFlash('success', 'Produit créé.');
            return $this->redirectToRoute('admin_product_index');
        }

        return $this->render('admin/product/form.html.twig', ['form' => $form, 'title' => 'Nouveau produit']);
    }

    #[Route('/{id}/edit', name: 'admin_product_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Product $product, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$product->getSlug()) {
                $product->setSlug(strtolower($slugger->slug($product->getName())->toString()));
            }
            $em->flush();
            $this->addFlash('success', 'Produit modifié.');
            return $this->redirectToRoute('admin_product_index');
        }

        return $this->render('admin/product/form.html.twig', ['form' => $form, 'title' => 'Modifier le produit']);
    }

    #[Route('/{id}', name: 'admin_product_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Product $product, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-product-'.$product->getId(), (string) $request->request->get('_token'))) {
            $em->remove($product);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        }

        return $this->redirectToRoute('admin_product_index');
    }
}
