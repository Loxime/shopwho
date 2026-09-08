<?php

namespace App\Controller;

use App\Service\SupportAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SupportController extends AbstractController
{
    private const MAX_MESSAGE_LENGTH = 500;

    #[Route(
        '/support/chat',
        name: 'app_support_chat',
        methods: ['POST']
    )]
    public function chat(
        Request $request,
        SupportAssistantService $assistant,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        try {
            $payload = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return $this->json(
                [
                    'error' =>
                        'Le corps de la requête doit contenir un JSON valide.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        if (
            !is_array($payload)
            || !array_key_exists(
                'message',
                $payload
            )
            || !is_string(
                $payload['message']
            )
        ) {
            return $this->json(
                [
                    'error' =>
                        'Le champ "message" est obligatoire et doit être une chaîne de caractères.',
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $message = $payload['message'];

        if (
            mb_strlen($message)
            > self::MAX_MESSAGE_LENGTH
        ) {
            return $this->json(
                [
                    'error' =>
                        sprintf(
                            'Le message ne peut pas dépasser %d caractères.',
                            self::MAX_MESSAGE_LENGTH
                        ),
                ],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $reply = $assistant->reply(
            $message
        );

        $action = null;

        if (
            $reply['actionLabel'] !== null
            && $reply['actionRoute'] !== null
        ) {
            $action = [
                'label' =>
                    $reply['actionLabel'],
                'url' =>
                    $urlGenerator->generate(
                        $reply['actionRoute']
                    ),
            ];
        }

        return $this->json([
            'topic' =>
                $reply['topic'],
            'message' =>
                $reply['message'],
            'action' =>
                $action,
        ]);
    }
}
