<?php

namespace App\Controller;

use App\Entity\SpecialOffer;
use App\Form\SpecialOfferType;
use App\Repository\SpecialOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/offers')]
class AdminSpecialOfferController extends AbstractController
{
    #[Route(
        '',
        name: 'admin_special_offer_index',
        methods: ['GET']
    )]
    public function index(
        SpecialOfferRepository $offers
    ): Response {
        return $this->render(
            'admin/special_offer/index.html.twig',
            [
                'offers' => $offers->findBy(
                    [],
                    [
                        'priority' => 'DESC',
                        'updatedAt' => 'DESC',
                    ]
                ),
            ]
        );
    }

    #[Route(
        '/new',
        name: 'admin_special_offer_new',
        methods: ['GET', 'POST']
    )]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $offer = new SpecialOffer();

        return $this->handleForm(
            $offer,
            $request,
            $em,
            true
        );
    }

    #[Route(
        '/{id}/edit',
        name: 'admin_special_offer_edit',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    public function edit(
        SpecialOffer $offer,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        return $this->handleForm(
            $offer,
            $request,
            $em,
            false
        );
    }

    #[Route(
        '/{id}/toggle',
        name: 'admin_special_offer_toggle',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function toggle(
        SpecialOffer $offer,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'toggle-special-offer-'
                    .$offer->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_special_offer_index'
            );
        }

        $offer->setIsActive(
            !$offer->isActive()
        );

        $em->flush();

        $this->addFlash(
            'success',
            $offer->isActive()
                ? 'Offre activée.'
                : 'Offre désactivée.'
        );

        return $this->redirectToRoute(
            'admin_special_offer_index'
        );
    }

    #[Route(
        '/{id}',
        name: 'admin_special_offer_delete',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function delete(
        SpecialOffer $offer,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'delete-special-offer-'
                    .$offer->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_special_offer_index'
            );
        }

        $em->remove($offer);
        $em->flush();

        $this->addFlash(
            'success',
            'Offre supprimée.'
        );

        return $this->redirectToRoute(
            'admin_special_offer_index'
        );
    }

    private function handleForm(
        SpecialOffer $offer,
        Request $request,
        EntityManagerInterface $em,
        bool $isNew
    ): Response {
        $form = $this->createForm(
            SpecialOfferType::class,
            $offer
        );

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $startsAt =
                $offer->getStartsAt();

            $endsAt =
                $offer->getEndsAt();

            if (
                $startsAt !== null
                && $endsAt !== null
                && $endsAt < $startsAt
            ) {
                $form
                    ->get('endsAt')
                    ->addError(
                        new FormError(
                            'La fin de diffusion doit être postérieure au début.'
                        )
                    );
            }

            if ($form->isValid()) {
                if ($isNew) {
                    $em->persist($offer);
                }

                $em->flush();

                $this->addFlash(
                    'success',
                    $isNew
                        ? 'Offre créée.'
                        : 'Offre modifiée.'
                );

                return $this->redirectToRoute(
                    'admin_special_offer_index'
                );
            }
        }

        return $this->render(
            'admin/special_offer/form.html.twig',
            [
                'form' => $form,
                'offer' => $offer,
                'title' => $isNew
                    ? 'Nouvelle offre'
                    : 'Modifier l’offre',
            ]
        );
    }
}
