<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\AddressType;
use App\Form\CustomerAddressType;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CustomerAddressController extends AbstractController
{
    #[Route(
        '/profil/adresses',
        name: 'app_profile_addresses',
        methods: ['GET', 'POST'],
    )]
    public function index(
        Request $request,
        AddressRepository $addresses,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $shippingAddress = $addresses->findForUserAndType(
            $user,
            AddressType::Shipping
        );

        $billingAddress = $addresses->findForUserAndType(
            $user,
            AddressType::Billing
        );

        if ($shippingAddress === null) {
            $shippingAddress = $this->createAddress(
                $user,
                AddressType::Shipping
            );
        }

        if ($billingAddress === null) {
            $billingAddress = $this->createAddress(
                $user,
                AddressType::Billing
            );
        }

        $shippingForm = $formFactory->createNamed(
            'shipping_address',
            CustomerAddressType::class,
            $shippingAddress
        );

        $billingForm = $formFactory->createNamed(
            'billing_address',
            CustomerAddressType::class,
            $billingAddress
        );

        $shippingForm->handleRequest($request);
        $billingForm->handleRequest($request);

        if (
            $shippingForm->isSubmitted()
            && $shippingForm->isValid()
        ) {
            $entityManager->persist($shippingAddress);
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Votre adresse de livraison a été enregistrée.'
            );

            return $this->redirectToRoute(
                'app_profile_addresses'
            );
        }

        if (
            $billingForm->isSubmitted()
            && $billingForm->isValid()
        ) {
            $entityManager->persist($billingAddress);
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Votre adresse de facturation a été enregistrée.'
            );

            return $this->redirectToRoute(
                'app_profile_addresses'
            );
        }

        return $this->render(
            'security/addresses.html.twig',
            [
                'shippingForm' => $shippingForm,
                'billingForm' => $billingForm,
            ]
        );
    }

    private function createAddress(
        User $user,
        AddressType $type
    ): Address {
        return (new Address($user, $type))
            ->setFirstName($user->getFirstName() ?? '')
            ->setLastName($user->getLastName() ?? '');
    }
}
