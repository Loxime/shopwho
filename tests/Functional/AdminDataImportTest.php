<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Import\DTO\ImportDtoFactory;
use App\Import\ImportTemplateGenerator;
use App\Import\Reader\JsonImportReader;
use App\Import\Reader\XlsxImportReader;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminDataImportTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true
        );
    }

    public function testAnonymousAccessIsRedirected(): void
    {
        static::createClient()->request(
            'GET',
            '/admin/data-import'
        );

        self::assertResponseRedirects(
            '/connexion'
        );
    }

    public function testAdminCanOpenImportPage(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $admin = $this->createAdmin(
            $em
        );

        $client->loginUser($admin);

        $crawler = $client->request(
            'GET',
            '/admin/data-import'
        );

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'h1',
            'Import de données'
        );

        self::assertCount(
            2,
            $crawler->filter(
                '.admin-data-import-template-actions a'
            )
        );

        $this->removeUser(
            $em,
            $admin->getEmail()
        );
    }

    public function testPreviewDoesNotPersistImportedUser(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $suffix = strtolower(
            bin2hex(random_bytes(6))
        );

        $admin = $this->createAdmin(
            $em,
            $suffix
        );

        $externalRef = 'USR-WEB-PREVIEW-'.$suffix;
        $email = 'preview-'.$suffix.'@example.test';

        $client->loginUser($admin);

        $crawler = $client->request(
            'GET',
            '/admin/data-import'
        );

        $token = (string) $crawler
            ->filter('input[name="_token"]')
            ->attr('value');

        $file = $this->createUserJson(
            $suffix,
            $externalRef,
            $email
        );

        try {
            $crawler = $client->request(
                'POST',
                '/admin/data-import',
                [
                    '_token' => $token,
                    'type' => 'users',
                    'mode' => 'preview',
                ],
                [
                    'import_file' => new UploadedFile(
                        $file,
                        'users.json',
                        'application/json',
                        null,
                        true
                    ),
                ]
            );
        } finally {
            @unlink($file);
        }

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.admin-flash-success',
            'aucune donnée n’a été enregistrée'
        );

        self::assertSelectorTextSame(
            '.admin-data-import-stats > div:nth-child(1) strong',
            '1'
        );

        self::assertSelectorTextSame(
            '.admin-data-import-stats > div:nth-child(2) strong',
            '1'
        );

        self::assertSelectorTextSame(
            '.admin-data-import-stats > div:nth-child(5) strong',
            '0'
        );

        /*
         * Le dry-run clear l'EntityManager après rollback.
         * On relit donc directement depuis le repository.
         */
        self::assertNull(
            $em->getRepository(User::class)
                ->findOneBy([
                    'externalRef' => $externalRef,
                ])
        );

        $this->removeUser(
            $em,
            $admin->getEmail()
        );
    }

    public function testAdminCanImportJsonUser(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $suffix = strtolower(
            bin2hex(random_bytes(6))
        );

        $admin = $this->createAdmin(
            $em,
            $suffix
        );

        $externalRef = 'USR-WEB-IMPORT-'.$suffix;
        $email = 'import-'.$suffix.'@example.test';

        $client->loginUser($admin);

        $crawler = $client->request(
            'GET',
            '/admin/data-import'
        );

        $token = (string) $crawler
            ->filter('input[name="_token"]')
            ->attr('value');

        $file = $this->createUserJson(
            $suffix,
            $externalRef,
            $email
        );

        try {
            $client->request(
                'POST',
                '/admin/data-import',
                [
                    '_token' => $token,
                    'type' => 'users',
                    'mode' => 'import',
                ],
                [
                    'import_file' => new UploadedFile(
                        $file,
                        'users.json',
                        'application/json',
                        null,
                        true
                    ),
                ]
            );
        } finally {
            @unlink($file);
        }

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.admin-flash-success',
            'Import terminé avec succès'
        );

        self::assertSelectorTextSame(
            '.admin-data-import-stats > div:nth-child(2) strong',
            '1'
        );

        $em->clear();

        $imported = $em
            ->getRepository(User::class)
            ->findOneBy([
                'externalRef' => $externalRef,
            ]);

        self::assertInstanceOf(
            User::class,
            $imported
        );

        self::assertSame(
            $email,
            $imported->getEmail()
        );

        $this->removeUser(
            $em,
            $email
        );

        $this->removeUser(
            $em,
            $admin->getEmail()
        );
    }

    public function testInvalidCsrfDoesNotImportAnything(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $suffix = strtolower(
            bin2hex(random_bytes(6))
        );

        $admin = $this->createAdmin(
            $em,
            $suffix
        );

        $externalRef = 'USR-WEB-CSRF-'.$suffix;
        $email = 'csrf-'.$suffix.'@example.test';

        $client->loginUser($admin);

        $file = $this->createUserJson(
            $suffix,
            $externalRef,
            $email
        );

        try {
            $client->request(
                'POST',
                '/admin/data-import',
                [
                    '_token' => 'invalid-token',
                    'type' => 'users',
                    'mode' => 'import',
                ],
                [
                    'import_file' => new UploadedFile(
                        $file,
                        'users.json',
                        'application/json',
                        null,
                        true
                    ),
                ]
            );
        } finally {
            @unlink($file);
        }

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            '.admin-flash-error',
            'jeton de sécurité est invalide'
        );

        self::assertNull(
            $em->getRepository(User::class)
                ->findOneBy([
                    'externalRef' => $externalRef,
                ])
        );

        $this->removeUser(
            $em,
            $admin->getEmail()
        );
    }

    public function testJsonTemplateEndpointReturnsUsableTemplate(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $admin = $this->createAdmin(
            $em
        );

        $client->loginUser($admin);

        $client->request(
            'GET',
            '/admin/data-import/template/users.json'
        );

        self::assertResponseIsSuccessful();

        self::assertResponseHeaderSame(
            'content-type',
            'application/json; charset=UTF-8'
        );

        self::assertStringContainsString(
            'shopwho-users-template.json',
            (string) $client
                ->getResponse()
                ->headers
                ->get('content-disposition')
        );

        $content = $client
            ->getResponse()
            ->getContent();

        self::assertIsString($content);

        $decoded = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertArrayHasKey(
            'users',
            $decoded
        );

        self::assertSame(
            'USR-EXAMPLE-001',
            $decoded['users'][0]['externalRef']
        );

        $this->removeUser(
            $em,
            $admin->getEmail()
        );
    }

    public function testInvalidTemplateTypeReturns404(): void
    {
        $client = static::createClient();
        $em = $this->em();

        $admin = $this->createAdmin(
            $em
        );

        $client->loginUser($admin);

        $client->request(
            'GET',
            '/admin/data-import/template/categories.json'
        );

        self::assertResponseStatusCodeSame(
            404
        );

        $this->removeUser(
            $em,
            $admin->getEmail()
        );
    }

    public function testGeneratedTemplatesCanBeReadByImportReaders(): void
    {
        $generator = new ImportTemplateGenerator();
        $factory = new ImportDtoFactory();

        $jsonFile = sprintf(
            '%s/shopwho-template-test-%s.json',
            sys_get_temp_dir(),
            bin2hex(random_bytes(6))
        );

        $xlsxFile = $generator->xlsx(
            'orders'
        );

        file_put_contents(
            $jsonFile,
            $generator->json('products')
        );

        try {
            $jsonPayload = (
                new JsonImportReader($factory)
            )->read(
                'products',
                $jsonFile
            );

            $xlsxPayload = (
                new XlsxImportReader($factory)
            )->read(
                'orders',
                $xlsxFile
            );
        } finally {
            @unlink($jsonFile);
            @unlink($xlsxFile);
        }

        self::assertCount(
            1,
            $jsonPayload->records
        );

        self::assertSame(
            'PROD-EXAMPLE-001',
            $jsonPayload
                ->records[0]
                ->externalRef
        );

        self::assertCount(
            1,
            $xlsxPayload->records
        );

        self::assertSame(
            'ORD-EXAMPLE-001',
            $xlsxPayload
                ->records[0]
                ->externalRef
        );

        self::assertCount(
            1,
            $xlsxPayload
                ->records[0]
                ->items
        );
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()
            ->get(
                EntityManagerInterface::class
            );

        return $em;
    }

    private function createAdmin(
        EntityManagerInterface $em,
        ?string $suffix = null
    ): User {
        $suffix ??= strtolower(
            bin2hex(random_bytes(6))
        );

        $admin = (new User())
            ->setEmail(
                'admin-import-'.$suffix
                    .'@example.test'
            )
            ->setPassword('unused')
            ->setRoles(['ROLE_ADMIN']);

        $em->persist($admin);
        $em->flush();

        return $admin;
    }

    private function createUserJson(
        string $suffix,
        string $externalRef,
        string $email
    ): string {
        $file = sprintf(
            '%s/shopwho-admin-import-%s.json',
            sys_get_temp_dir(),
            $suffix
        );

        file_put_contents(
            $file,
            json_encode(
                [
                    'users' => [
                        [
                            'externalRef' => $externalRef,
                            'email' => $email,
                            'firstName' => 'Import',
                            'lastName' => 'Test',
                            'createdAt' =>
                                '2026-08-28T12:00:00+00:00',
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT
                | JSON_THROW_ON_ERROR
            )
        );

        return $file;
    }

    private function removeUser(
        EntityManagerInterface $em,
        string $email
    ): void {
        $user = $em
            ->getRepository(User::class)
            ->findOneBy([
                'email' => $email,
            ]);

        if ($user instanceof User) {
            $em->remove($user);
            $em->flush();
        }
    }
}
