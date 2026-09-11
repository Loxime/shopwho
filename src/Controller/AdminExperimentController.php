<?php

namespace App\Controller;

use App\Repository\ExperimentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
