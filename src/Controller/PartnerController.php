<?php

namespace App\Controller;

use App\Repository\PartnerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PartnerController extends AbstractController
{
    #[Route(
        '/partenaires',
        name: 'app_partner_index',
        methods: ['GET']
    )]
    public function index(
        PartnerRepository $partners
    ): Response {
        return $this->render(
            'partner/index.html.twig',
            [
                'partners' =>
                    $partners->findActive(),
            ]
        );
    }
}
