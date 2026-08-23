<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/profil', name: 'app_profile', methods: ['GET'])]
    public function profile(OrderRepository $orders): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('security/profile.html.twig', [
            'user' => $user,
            'orders' => $orders->findRecentForUser($user),
        ]);
    }

    #[Route('/profil/commandes', name: 'app_profile_orders', methods: ['GET'])]
    public function orders(OrderRepository $orders): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('security/orders.html.twig', [
            'orders' => $orders->findAllForUser($user),
        ]);
    }

    #[Route('/profil/commandes/{reference}', name: 'app_profile_order_show', methods: ['GET'])]
    public function orderShow(string $reference, OrderRepository $orders): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $order = $orders->findOneByReferenceAndUser($reference, $user);
        if (!$order) {
            throw $this->createNotFoundException();
        }

        return $this->render('security/order_show.html.twig', ['order' => $order]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Cette méthode est interceptée par le firewall Symfony.');
    }
}
