<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profil/notifications')]
final class NotificationController extends AbstractController
{
    #[Route(
        '',
        name: 'app_profile_notifications',
        methods: ['GET']
    )]
    public function index(
        NotificationRepository $notifications
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->render(
            'profile/notifications.html.twig',
            [
                'notifications' =>
                    $notifications->findForUser(
                        $user
                    ),
                'unreadCount' =>
                    $notifications
                        ->countUnreadForUser(
                            $user
                        ),
            ]
        );
    }

    #[Route(
        '/{id}/open',
        name: 'app_profile_notification_open',
        requirements: [
            'id' => '\d+',
        ],
        methods: ['POST']
    )]
    public function open(
        Notification $notification,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if (
            !$user instanceof User
            || $notification
                ->getUser()
                ->getId()
                !== $user->getId()
        ) {
            throw $this->createNotFoundException();
        }

        if (
            !$this->isCsrfTokenValid(
                'open-notification-'
                    .$notification->getId(),
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        if (!$notification->isRead()) {
            $notification->markAsRead();
            $em->flush();
        }

        $targetUrl =
            $notification->getTargetUrl();

        if (
            $targetUrl !== null
            && str_starts_with(
                $targetUrl,
                '/'
            )
        ) {
            return $this->redirect(
                $targetUrl
            );
        }

        return $this->redirectToRoute(
            'app_profile_notifications'
        );
    }

    #[Route(
        '/read-all',
        name: 'app_profile_notifications_read_all',
        methods: ['POST']
    )]
    public function markAllAsRead(
        Request $request,
        NotificationRepository $notifications,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (
            !$this->isCsrfTokenValid(
                'read-all-notifications',
                (string) $request
                    ->request
                    ->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException(
                'Jeton CSRF invalide.'
            );
        }

        foreach (
            $notifications
                ->findUnreadForUser($user)
            as $notification
        ) {
            $notification->markAsRead();
        }

        $em->flush();

        $this->addFlash(
            'success',
            'Toutes les notifications ont été marquées comme lues.'
        );

        return $this->redirectToRoute(
            'app_profile_notifications'
        );
    }
}
