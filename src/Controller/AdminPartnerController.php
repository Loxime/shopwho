<?php

namespace App\Controller;

use App\Entity\Partner;
use App\Form\PartnerType;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/partners')]
final class AdminPartnerController extends AbstractController
{
    #[Route(
        '',
        name: 'admin_partner_index',
        methods: ['GET']
    )]
    public function index(
        PartnerRepository $partners
    ): Response {
        return $this->render(
            'admin/partner/index.html.twig',
            [
                'partners' =>
                    $partners->findBy(
                        [],
                        [
                            'priority' => 'DESC',
                            'name' => 'ASC',
                        ]
                    ),
            ]
        );
    }

    #[Route(
        '/new',
        name: 'admin_partner_new',
        methods: ['GET', 'POST']
    )]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        return $this->handleForm(
            new Partner(),
            $request,
            $em,
            true
        );
    }

    #[Route(
        '/{id}/edit',
        name: 'admin_partner_edit',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    public function edit(
        Partner $partner,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        return $this->handleForm(
            $partner,
            $request,
            $em,
            false
        );
    }

    #[Route(
        '/{id}/toggle',
        name: 'admin_partner_toggle',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function toggle(
        Partner $partner,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'toggle-partner-'
                    .$partner->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_partner_index'
            );
        }

        $partner->setIsActive(
            !$partner->isActive()
        );

        $em->flush();

        $this->addFlash(
            'success',
            $partner->isActive()
                ? 'Partenaire activé.'
                : 'Partenaire désactivé.'
        );

        return $this->redirectToRoute(
            'admin_partner_index'
        );
    }

    #[Route(
        '/{id}',
        name: 'admin_partner_delete',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function delete(
        Partner $partner,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            $this->isCsrfTokenValid(
                'delete-partner-'
                    .$partner->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            $em->remove($partner);
            $em->flush();

            $this->addFlash(
                'success',
                'Partenaire supprimé.'
            );
        }

        return $this->redirectToRoute(
            'admin_partner_index'
        );
    }

    private function handleForm(
        Partner $partner,
        Request $request,
        EntityManagerInterface $em,
        bool $isNew
    ): Response {
        $form = $this->createForm(
            PartnerType::class,
            $partner
        );

        $form->handleRequest(
            $request
        );

        if (
            $form->isSubmitted()
            && $form->isValid()
        ) {
            if ($isNew) {
                $em->persist(
                    $partner
                );
            }

            $em->flush();

            $this->addFlash(
                'success',
                $isNew
                    ? 'Partenaire créé.'
                    : 'Partenaire modifié.'
            );

            return $this->redirectToRoute(
                'admin_partner_index'
            );
        }

        return $this->render(
            'admin/partner/form.html.twig',
            [
                'form' => $form,
                'partner' => $partner,
                'title' => $isNew
                    ? 'Nouveau partenaire'
                    : 'Modifier le partenaire',
            ]
        );
    }
}
