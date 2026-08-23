<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConsentController extends AbstractController
{
    #[Route('/tracking/consent/{choice}', name: 'app_tracking_consent', requirements: ['choice' => 'accept|refuse'], methods: ['POST'])]
    public function consent(string $choice, Request $request): Response
    {
        $target = $request->headers->get('referer') ?: $this->generateUrl('app_home');
        $response = $this->redirect($target);
        $response->headers->setCookie(Cookie::create(
            name: 'shopwho_tracking_consent',
            value: $choice === 'accept' ? 'yes' : 'no',
            expire: new \DateTimeImmutable('+180 days'),
            path: '/',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }
}
