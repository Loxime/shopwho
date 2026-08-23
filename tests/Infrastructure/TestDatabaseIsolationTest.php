<?php

namespace App\Tests\Infrastructure;

use App\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class TestDatabaseIsolationTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testTestEnvironmentUsesAnIsolatedDatabase(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $databaseName = $connection->fetchOne('SELECT current_database()');

        self::assertIsString($databaseName);
        self::assertMatchesRegularExpression('/_test.*$/', $databaseName);
        self::assertNotSame('shopwho', $databaseName);
    }
}
