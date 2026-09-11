<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminDashboardController extends AbstractController
{
    #[Route(
        '/admin',
        name: 'admin_dashboard',
        methods: ['GET']
    )]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(
            'ROLE_BACKOFFICE'
        );

        if (
            $this->isGranted(
                'ROLE_CATALOG_MANAGER'
            )
        ) {
            return $this->redirectToRoute(
                'admin_product_index'
            );
        }

        if (
            $this->isGranted(
                'ROLE_MARKETING_MANAGER'
            )
        ) {
            return $this->redirectToRoute(
                'admin_special_offer_index'
            );
        }

        if (
            $this->isGranted(
                'ROLE_EXPERIMENT_MANAGER'
            )
        ) {
            return $this->redirectToRoute(
                'admin_experiment_index'
            );
        }

        if (
            $this->isGranted(
                'ROLE_DATA_ANALYST'
            )
        ) {
            return $this->redirectToRoute(
                'admin_analytics_index'
            );
        }

        if (
            $this->isGranted(
                'ROLE_DATA_MANAGER'
            )
        ) {
            return $this->redirectToRoute(
                'admin_data_import'
            );
        }

        throw $this->createAccessDeniedException();
    }
}
