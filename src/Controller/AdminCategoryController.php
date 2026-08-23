<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/categories')]
class AdminCategoryController extends AbstractController
{
    #[Route('', name: 'admin_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categories): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories->findBy([], ['navigationPosition' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $category = new Category();

        return $this->handleForm($category, $request, $em, $slugger, true);
    }

    #[Route('/{id}/edit', name: 'admin_category_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Category $category, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        return $this->handleForm($category, $request, $em, $slugger, false);
    }

    #[Route('/{id}', name: 'admin_category_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-category-'.$category->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_category_index');
        }

        if ($category->hasProducts()) {
            $this->addFlash('error', 'Cette catégorie contient des produits. Réaffectez-les avant de la supprimer.');
        } else {
            $em->remove($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('admin_category_index');
    }

    private function handleForm(
        Category $category,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        bool $isNew,
    ): Response {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$category->getSlug()) {
                $category->setSlug(strtolower($slugger->slug($category->getName())->toString()));
            }
            if ($isNew) {
                $em->persist($category);
            }
            $em->flush();
            $this->addFlash('success', $isNew ? 'Catégorie créée.' : 'Catégorie modifiée.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/form.html.twig', [
            'form' => $form,
            'title' => $isNew ? 'Nouvelle catégorie' : 'Modifier la catégorie',
        ]);
    }
}
