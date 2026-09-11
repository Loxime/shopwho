<?php

namespace App\Controller;

use App\Entity\Experiment;
use App\Entity\ExperimentVariant;
use App\Enum\ExperimentStatus;
use App\Form\ExperimentType;
use App\Form\ExperimentVariantType;
use App\Repository\ExperimentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/experiments')]
final class AdminExperimentController extends AbstractController
{
    #[Route(
        '',
        name: 'admin_experiment_index',
        methods: ['GET']
    )]
    public function index(
        ExperimentRepository $experiments
    ): Response {
        return $this->render(
            'admin/experiment/index.html.twig',
            [
                'experiments' =>
                    $experiments->findBy(
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
        name: 'admin_experiment_new',
        methods: ['GET', 'POST']
    )]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        return $this->handleExperimentForm(
            new Experiment(),
            $request,
            $em,
            true
        );
    }

    #[Route(
        '/{id}/edit',
        name: 'admin_experiment_edit',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    public function edit(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            $experiment->getStatus()
            !== ExperimentStatus::Draft
        ) {
            $this->addFlash(
                'error',
                'La structure d’une expérience ayant déjà démarré est verrouillée.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        return $this->handleExperimentForm(
            $experiment,
            $request,
            $em,
            false
        );
    }

    #[Route(
        '/{id}',
        name: 'admin_experiment_delete',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function delete(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            $experiment->getStatus()
            !== ExperimentStatus::Draft
        ) {
            $this->addFlash(
                'error',
                'Seule une expérience en brouillon peut être supprimée.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            !$this->isCsrfTokenValid(
                'delete-experiment-'
                    .$experiment->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        $em->remove($experiment);
        $em->flush();

        $this->addFlash(
            'success',
            'Expérience supprimée.'
        );

        return $this->redirectToRoute(
            'admin_experiment_index'
        );
    }

    #[Route(
        '/{id}/variants/new',
        name: 'admin_experiment_variant_new',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    public function newVariant(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            $experiment->getStatus()
            !== ExperimentStatus::Draft
        ) {
            return $this->lockedExperimentRedirect();
        }

        $variant =
            (new ExperimentVariant())
                ->setExperiment(
                    $experiment
                );

        $form = $this->createForm(
            ExperimentVariantType::class,
            $variant
        );

        $form->handleRequest(
            $request
        );

        if (
            $form->isSubmitted()
            && $form->isValid()
        ) {
            $experiment->addVariant(
                $variant
            );

            $em->persist(
                $variant
            );

            $em->flush();

            $this->addFlash(
                'success',
                'Variante ajoutée.'
            );

            return $this->redirectToRoute(
                'admin_experiment_edit',
                [
                    'id' =>
                        $experiment->getId(),
                ]
            );
        }

        return $this->render(
            'admin/experiment/variant_form.html.twig',
            [
                'form' => $form,
                'experiment' =>
                    $experiment,
                'variant' => $variant,
                'title' =>
                    'Nouvelle variante',
            ]
        );
    }

    #[Route(
        '/{id}/variants/{variantId}/edit',
        name: 'admin_experiment_variant_edit',
        requirements: [
            'id' => '\d+',
            'variantId' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    public function editVariant(
        Experiment $experiment,
        int $variantId,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            $experiment->getStatus()
            !== ExperimentStatus::Draft
        ) {
            return $this->lockedExperimentRedirect();
        }

        $variant = $this->findVariant(
            $experiment,
            $variantId,
            $em
        );

        $form = $this->createForm(
            ExperimentVariantType::class,
            $variant
        );

        $form->handleRequest(
            $request
        );

        if (
            $form->isSubmitted()
            && $form->isValid()
        ) {
            $em->flush();

            $this->addFlash(
                'success',
                'Variante modifiée.'
            );

            return $this->redirectToRoute(
                'admin_experiment_edit',
                [
                    'id' =>
                        $experiment->getId(),
                ]
            );
        }

        return $this->render(
            'admin/experiment/variant_form.html.twig',
            [
                'form' => $form,
                'experiment' =>
                    $experiment,
                'variant' => $variant,
                'title' =>
                    'Modifier la variante',
            ]
        );
    }

    #[Route(
        '/{id}/variants/{variantId}',
        name: 'admin_experiment_variant_delete',
        requirements: [
            'id' => '\d+',
            'variantId' => '\d+',
        ],
        methods: ['POST']
    )]
    public function deleteVariant(
        Experiment $experiment,
        int $variantId,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            $experiment->getStatus()
            !== ExperimentStatus::Draft
        ) {
            return $this->lockedExperimentRedirect();
        }

        $variant = $this->findVariant(
            $experiment,
            $variantId,
            $em
        );

        if (
            $this->isCsrfTokenValid(
                'delete-experiment-variant-'
                    .$variant->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            $experiment->removeVariant(
                $variant
            );

            $em->remove(
                $variant
            );

            $em->flush();

            $this->addFlash(
                'success',
                'Variante supprimée.'
            );
        }

        return $this->redirectToRoute(
            'admin_experiment_edit',
            [
                'id' =>
                    $experiment->getId(),
            ]
        );
    }

    #[Route(
        '/{id}/start',
        name: 'admin_experiment_start',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function start(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'start-experiment-'
                    .$experiment->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $experiment->getStatus()
            !== ExperimentStatus::Draft
        ) {
            $this->addFlash(
                'error',
                'Seule une expérience en brouillon peut être démarrée.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $experiment->getVariants()->count()
            < 2
        ) {
            $this->addFlash(
                'error',
                'Une expérience doit contenir au moins deux variantes avant son démarrage.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $experiment->getTrafficPercentage()
            <= 0
        ) {
            $this->addFlash(
                'error',
                'Le trafic exposé doit être supérieur à 0 % avant le démarrage.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        $now = new \DateTimeImmutable();

        if (
            $experiment->getEndsAt() !== null
            && $experiment->getEndsAt() <= $now
        ) {
            $this->addFlash(
                'error',
                'La date de fin de l’expérience est déjà dépassée.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $experiment->getStartsAt()
            === null
        ) {
            $experiment->setStartsAt(
                $now
            );
        }

        $experiment->setStatus(
            ExperimentStatus::Running
        );

        $em->flush();

        $this->addFlash(
            'success',
            'Expérience démarrée.'
        );

        return $this->redirectToRoute(
            'admin_experiment_index'
        );
    }

    #[Route(
        '/{id}/pause',
        name: 'admin_experiment_pause',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function pause(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'pause-experiment-'
                    .$experiment->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $experiment->getStatus()
            !== ExperimentStatus::Running
        ) {
            $this->addFlash(
                'error',
                'Seule une expérience en cours peut être mise en pause.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        $experiment->setStatus(
            ExperimentStatus::Paused
        );

        $em->flush();

        $this->addFlash(
            'success',
            'Expérience mise en pause.'
        );

        return $this->redirectToRoute(
            'admin_experiment_index'
        );
    }

    #[Route(
        '/{id}/resume',
        name: 'admin_experiment_resume',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function resume(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'resume-experiment-'
                    .$experiment->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $experiment->getStatus()
            !== ExperimentStatus::Paused
        ) {
            $this->addFlash(
                'error',
                'Seule une expérience en pause peut être reprise.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $experiment->getEndsAt() !== null
            && $experiment->getEndsAt()
                <= new \DateTimeImmutable()
        ) {
            $this->addFlash(
                'error',
                'Cette expérience a atteint sa date de fin.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        $experiment->setStatus(
            ExperimentStatus::Running
        );

        $em->flush();

        $this->addFlash(
            'success',
            'Expérience reprise.'
        );

        return $this->redirectToRoute(
            'admin_experiment_index'
        );
    }

    #[Route(
        '/{id}/end',
        name: 'admin_experiment_end',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function end(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isCsrfTokenValid(
                'end-experiment-'
                    .$experiment->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            !in_array(
                $experiment->getStatus(),
                [
                    ExperimentStatus::Running,
                    ExperimentStatus::Paused,
                ],
                true
            )
        ) {
            $this->addFlash(
                'error',
                'Cette expérience ne peut pas être terminée dans son état actuel.'
            );

            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        $now = new \DateTimeImmutable();

        $experiment->setStatus(
            ExperimentStatus::Ended
        );

        if (
            $experiment->getEndsAt() === null
            || $experiment->getEndsAt() > $now
        ) {
            $experiment->setEndsAt(
                $now
            );
        }

        $em->flush();

        $this->addFlash(
            'success',
            'Expérience terminée.'
        );

        return $this->redirectToRoute(
            'admin_experiment_index'
        );
    }

    private function handleExperimentForm(
        Experiment $experiment,
        Request $request,
        EntityManagerInterface $em,
        bool $isNew
    ): Response {
        $form = $this->createForm(
            ExperimentType::class,
            $experiment
        );

        $form->handleRequest(
            $request
        );

        if ($form->isSubmitted()) {
            $startsAt =
                $experiment->getStartsAt();

            $endsAt =
                $experiment->getEndsAt();

            if (
                $startsAt !== null
                && $endsAt !== null
                && $endsAt < $startsAt
            ) {
                $form
                    ->get('endsAt')
                    ->addError(
                        new FormError(
                            'La fin doit être postérieure au début de l’expérience.'
                        )
                    );
            }
        }

        if (
            $form->isSubmitted()
            && $form->isValid()
        ) {
            if ($isNew) {
                $em->persist(
                    $experiment
                );
            }

            $em->flush();

            $this->addFlash(
                'success',
                $isNew
                    ? 'Expérience créée. Ajoutez maintenant ses variantes.'
                    : 'Expérience modifiée.'
            );

            return $this->redirectToRoute(
                'admin_experiment_edit',
                [
                    'id' =>
                        $experiment->getId(),
                ]
            );
        }

        return $this->render(
            'admin/experiment/form.html.twig',
            [
                'form' => $form,
                'experiment' =>
                    $experiment,
                'title' => $isNew
                    ? 'Nouvelle expérience'
                    : 'Modifier l’expérience',
                'isNew' => $isNew,
            ]
        );
    }

    private function findVariant(
        Experiment $experiment,
        int $variantId,
        EntityManagerInterface $em
    ): ExperimentVariant {
        $variant = $em
            ->getRepository(
                ExperimentVariant::class
            )
            ->find(
                $variantId
            );

        if (
            !$variant instanceof ExperimentVariant
            || $variant->getExperiment()?->getId()
                !== $experiment->getId()
        ) {
            throw $this
                ->createNotFoundException(
                    'Variante introuvable.'
                );
        }

        return $variant;
    }

    private function lockedExperimentRedirect():
        Response
    {
        $this->addFlash(
            'error',
            'Les variantes d’une expérience ayant déjà démarré sont verrouillées.'
        );

        return $this->redirectToRoute(
            'admin_experiment_index'
        );
    }
}
