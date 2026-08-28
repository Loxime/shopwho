<?php

namespace App\Controller;

use App\Repository\ReviewRepository;
use App\Review\AdminReviewFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reviews')]
final class AdminReviewController extends AbstractController
{
    #[Route(
        '',
        name: 'admin_review_index',
        methods: ['GET']
    )]
    public function index(
        Request $request,
        ReviewRepository $reviews
    ): Response {
        $filter = new AdminReviewFilter(
            search: $request->query->getString('q'),
            rating: $request->query->getInt('rating') ?: null,
            source: $request->query->getString(
                'source',
                AdminReviewFilter::SOURCE_ALL
            ),
            page: $request->query->getInt('page', 1),
            perPage: 25
        );

        $reviewPage = $reviews->searchForAdmin(
            $filter
        );

        return $this->render(
            'admin/review/index.html.twig',
            [
                'reviewPage' => $reviewPage,
                'filter' => $filter,
            ]
        );
    }
}
