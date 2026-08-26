<?php

namespace App\Controller;

use App\Form\ProfileType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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


#[Route('/profil/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
public function editProfile(
    Request $request,
    UserRepository $users,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $entityManager,
): Response {
    /** @var User $user */
    $user = $this->getUser();

    $form = $this->createForm(ProfileType::class, [
        'firstName' => $user->getFirstName(),
        'lastName' => $user->getLastName(),
        'email' => $user->getEmail(),
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $currentPassword = (string) $form->get('currentPassword')->getData();

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $form->get('currentPassword')->addError(
                new FormError('Le mot de passe actuel est incorrect.')
            );
        }

        $email = strtolower(trim(
            (string) $form->get('email')->getData()
        ));

        $existingUser = $users->findOneBy([
            'email' => $email,
        ]);

        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $form->get('email')->addError(
                new FormError('Cette adresse e-mail est déjà utilisée.')
            );
        }

        if ($form->isValid()) {
            $user
                ->setFirstName(
                    (string) $form->get('firstName')->getData()
                )
                ->setLastName(
                    (string) $form->get('lastName')->getData()
                )
                ->setEmail($email);

            $newPassword = $form->get('newPassword')->getData();

            if (is_string($newPassword) && $newPassword !== '') {
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $newPassword)
                );
            }

            $entityManager->flush();

            $this->addFlash(
                'success',
                'Vos informations ont été mises à jour.'
            );

            return $this->redirectToRoute('app_profile');
        }
    }

    return $this->render('security/profile_edit.html.twig', [
        'profileForm' => $form,
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
