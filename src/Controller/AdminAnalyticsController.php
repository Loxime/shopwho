<?php

namespace App\Controller;

use App\Analytics\AnalyticsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/analytics')]
final class AdminAnalyticsController extends AbstractController
{
    private const ALLOWED_PERIODS = [
        7,
        30,
        90,
    ];

    #[Route(
        '',
        name: 'admin_analytics_index',
        methods: ['GET']
    )]
    public function index(
        Request $request,
        AnalyticsQuery $analytics
    ): Response {
        $days = $request->query->getInt(
            'days',
            30
        );

        if (
            !in_array(
                $days,
                self::ALLOWED_PERIODS,
                true
            )
        ) {
            $days = 30;
        }

        $to = new \DateTimeImmutable();

        $from = $to->modify(
            sprintf(
                '-%d days',
                $days
            )
        );

        $overview = $analytics->overview(
            $from,
            $to
        );

        return $this->render(
            'admin/analytics/index.html.twig',
            [
                'overview' => $overview,
                'days' => $days,
                'periods' =>
                    self::ALLOWED_PERIODS,
            ]
        );
    }
}
