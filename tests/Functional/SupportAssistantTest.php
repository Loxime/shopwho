<?php

namespace App\Tests\Functional;

use App\Kernel;
use App\Service\SupportAssistantService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class SupportAssistantTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true
        );
    }

    public function testAssistantRecognizesOrderQuestion(): void
    {
        self::bootKernel();

        $assistant = static::getContainer()->get(
            SupportAssistantService::class
        );

        $reply = $assistant->reply(
            'Où sont mes commandes ?'
        );

        self::assertSame(
            'orders',
            $reply['topic']
        );

        self::assertSame(
            'Voir mes commandes',
            $reply['actionLabel']
        );

        self::assertSame(
            'app_profile_orders',
            $reply['actionRoute']
        );
    }

    public function testPaymentQuestionHasPriorityOverOrderTopic(): void
    {
        self::bootKernel();

        $assistant = static::getContainer()->get(
            SupportAssistantService::class
        );

        $reply = $assistant->reply(
            'Est-ce que ma commande a été payée ?'
        );

        self::assertSame(
            'payment',
            $reply['topic']
        );

        self::assertStringContainsString(
            'aucun paiement réel',
            $reply['message']
        );
    }

    public function testUnknownQuestionUsesFallback(): void
    {
        self::bootKernel();

        $assistant = static::getContainer()->get(
            SupportAssistantService::class
        );

        $reply = $assistant->reply(
            'Quelle est la capitale de Neptune ?'
        );

        self::assertSame(
            'fallback',
            $reply['topic']
        );

        self::assertNull(
            $reply['actionLabel']
        );

        self::assertNull(
            $reply['actionRoute']
        );
    }

    public function testEmptyQuestionReturnsWelcomeMessage(): void
    {
        self::bootKernel();

        $assistant = static::getContainer()->get(
            SupportAssistantService::class
        );

        $reply = $assistant->reply(
            '   '
        );

        self::assertSame(
            'welcome',
            $reply['topic']
        );
    }

    public function testOrderEndpointReturnsGeneratedActionUrl(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/support/chat',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                [
                    'message' =>
                        'Où sont mes commandes ?',
                ],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse(
            $client->getResponse()->getContent()
        );

        self::assertSame(
            'orders',
            $payload['topic']
        );

        self::assertSame(
            'Voir mes commandes',
            $payload['action']['label']
        );

        self::assertSame(
            '/profil/commandes',
            $payload['action']['url']
        );
    }

    public function testPrivacyEndpointReturnsNoAction(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/support/chat',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'message' =>
                        'Comment fonctionne le RGPD ?',
                ],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseIsSuccessful();

        $payload = $this->jsonResponse(
            $client->getResponse()->getContent()
        );

        self::assertSame(
            'privacy',
            $payload['topic']
        );

        self::assertNull(
            $payload['action']
        );
    }

    public function testInvalidJsonReturnsBadRequest(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/support/chat',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{invalid'
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST
        );

        $payload = $this->jsonResponse(
            $client->getResponse()->getContent()
        );

        self::assertStringContainsString(
            'JSON valide',
            $payload['error']
        );
    }

    public function testMissingMessageReturnsBadRequest(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/support/chat',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{}'
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST
        );

        $payload = $this->jsonResponse(
            $client->getResponse()->getContent()
        );

        self::assertStringContainsString(
            'message',
            $payload['error']
        );
    }

    public function testNonStringMessageReturnsBadRequest(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/support/chat',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'message' => 42,
                ],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST
        );
    }

    public function testMessageLongerThanLimitIsRejected(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/support/chat',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(
                [
                    'message' =>
                        str_repeat('a', 501),
                ],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $payload = $this->jsonResponse(
            $client->getResponse()->getContent()
        );

        self::assertStringContainsString(
            '500 caractères',
            $payload['error']
        );
    }

    public function testStorefrontDisplaysSupportChatWidget(): void
    {
        $client = static::createClient();

        $crawler = $client->request(
            'GET',
            '/'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorCount(
            1,
            '[data-support-chat]'
        );

        self::assertSame(
            '/support/chat',
            $crawler
                ->filter('[data-support-chat]')
                ->attr('data-endpoint')
        );

        self::assertSelectorCount(
            1,
            '[data-support-chat-toggle]'
        );

        self::assertSelectorTextContains(
            '[data-support-chat-toggle]',
            'Besoin d’aide ?'
        );

        self::assertSelectorExists(
            'textarea[data-support-chat-input][maxlength="500"]'
        );

        self::assertSelectorExists(
            'script[src="/js/support-chat.js"]'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(
        string|false $content
    ): array {
        self::assertIsString(
            $content
        );

        $payload = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertIsArray(
            $payload
        );

        return $payload;
    }
}
