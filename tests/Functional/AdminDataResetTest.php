<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\DataOrigin;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

final class AdminDataResetTest extends WebTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testAnonymousAccessIsRedirected(): void
    {
        static::createClient()->request('GET', '/admin/data-reset');
        self::assertResponseRedirects('/connexion');
    }

    public function testAdminPreviewDoesNotDeleteAndValidApplyIsOneTime(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = strtolower(bin2hex(random_bytes(6)));
        $admin = (new User())->setEmail('admin-reset-'.$suffix.'@example.test')->setPassword('unused')->setRoles(['ROLE_ADMIN']);
        $target = (new User())->setEmail('reset-'.$suffix.'@example.test')->setPassword('unused')->setExternalRef('USR-FICTION-WEB-'.$suffix)->setDataOrigin(DataOrigin::Imported);
        $native = (new User())->setEmail('native-reset-'.$suffix.'@example.test')->setPassword('unused')->setExternalRef('USR-FICTION-NATIVE-WEB-'.$suffix);
        $em->persist($admin);$em->persist($target);$em->persist($native);$em->flush();
        $targetId = $target->getId();
        $client->loginUser($admin);
        $file = sys_get_temp_dir().'/shopwho-reset-web-'.$suffix.'.json';
        file_put_contents($file, json_encode(['users' => [['externalRef' => $target->getExternalRef()], ['externalRef' => $native->getExternalRef()]]], JSON_THROW_ON_ERROR));

        $crawler = $client->request('POST', '/admin/data-reset', ['type' => 'users'], ['reset_file' => new UploadedFile($file, 'reset-users.json', 'application/json', null, true)]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Supprimables : 1');
        self::assertSelectorTextContains('body', 'Protégés : 1');
        self::assertNotNull($em->getRepository(User::class)->find($targetId));

        $form = $crawler->selectButton('Confirmer et appliquer')->form();
        $applyUri = $form->getUri();
        $values = $form->getPhpValues();
        $client->request('POST', $applyUri, [...$values, '_token' => 'invalid']);
        self::assertResponseRedirects('/admin/data-reset');
        self::assertNotNull($em->getRepository(User::class)->find($targetId));

        $client->request('POST', $applyUri, $values);
        self::assertResponseRedirects('/admin/data-reset/result');
        $em->clear();
        self::assertNull($em->getRepository(User::class)->find($targetId));

        $client->request('POST', $applyUri, $values);
        self::assertResponseRedirects('/admin/data-reset');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Prévisualisation invalide ou expirée');

        $remainingAdmin = $em->getRepository(User::class)->findOneBy(['email' => 'admin-reset-'.$suffix.'@example.test']);
        $remainingNative = $em->getRepository(User::class)->findOneBy(['email' => 'native-reset-'.$suffix.'@example.test']);
        if ($remainingAdmin) {$em->remove($remainingAdmin);}
        if ($remainingNative) {$em->remove($remainingNative);}
        $em->flush();
    }
}
