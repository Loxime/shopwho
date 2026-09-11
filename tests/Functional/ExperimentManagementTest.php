<?php

namespace App\Tests\Functional;

use App\Entity\Experiment;
use App\Entity\ExperimentVariant;
use App\Entity\User;
use App\Enum\ExperimentStatus;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class ExperimentManagementTest extends WebTestCase
{
    protected static function createKernel(
        array $options = []
    ): KernelInterface {
        return new Kernel(
            $options['environment'] ?? 'test',
            $options['debug'] ?? true
        );
    }

    protected function tearDown(): void
    {
        if (!static::$booted) {
            static::bootKernel();
        }

        $connection = $this->em()
            ->getConnection();

        $connection->executeStatement(
            "
                DELETE FROM ab_experiment
                WHERE experiment_key LIKE
                    'test-experiment-%'
            "
        );

        $connection->executeStatement(
            "
                DELETE FROM app_user
                WHERE email LIKE
                    'experiment-manager-%@shopwho.test'
            "
        );

        $this->em()->clear();

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testManagerCanCreateDraftAndAddVariant(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createManager()
        );

        $crawler = $client->request(
            'GET',
            '/admin/experiments/new'
        );

        self::assertResponseIsSuccessful();

        $client->submit(
            $crawler
                ->selectButton('Enregistrer')
                ->form([
                    'experiment[key]' =>
                        'test-experiment-create',
                    'experiment[name]' =>
                        'Expérience de création',
                    'experiment[trafficPercentage]' =>
                        100,
                ])
        );

        $experiment = $this
            ->em()
            ->getRepository(
                Experiment::class
            )
            ->findOneBy([
                'key' =>
                    'test-experiment-create',
            ]);

        self::assertInstanceOf(
            Experiment::class,
            $experiment
        );

        self::assertSame(
            ExperimentStatus::Draft,
            $experiment->getStatus()
        );

        self::assertResponseRedirects(
            '/admin/experiments/'
                .$experiment->getId()
                .'/edit'
        );

        $crawler = $client->request(
            'GET',
            '/admin/experiments/'
                .$experiment->getId()
                .'/variants/new'
        );

        self::assertResponseIsSuccessful();

        $client->submit(
            $crawler
                ->selectButton('Enregistrer')
                ->form([
                    'experiment_variant[key]' =>
                        'control',
                    'experiment_variant[name]' =>
                        'Contrôle',
                    'experiment_variant[weight]' =>
                        50,
                ])
        );

        self::assertResponseRedirects(
            '/admin/experiments/'
                .$experiment->getId()
                .'/edit'
        );

        $this->em()->clear();

        $persisted = $this
            ->em()
            ->getRepository(
                Experiment::class
            )
            ->find(
                $experiment->getId()
            );

        self::assertInstanceOf(
            Experiment::class,
            $persisted
        );

        self::assertCount(
            1,
            $persisted->getVariants()
        );

        $variant =
            $persisted->getVariants()->first();

        self::assertInstanceOf(
            ExperimentVariant::class,
            $variant
        );

        self::assertSame(
            'control',
            $variant->getKey()
        );

        self::assertSame(
            50,
            $variant->getWeight()
        );
    }

    public function testManagerCanEditDraftExperimentAndVariant(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createManager()
        );

        $experiment =
            $this->createExperiment(
                'test-experiment-edit'
            );

        $variant =
            $this->createVariant(
                $experiment,
                'control',
                50
            );

        $crawler = $client->request(
            'GET',
            '/admin/experiments/'
                .$experiment->getId()
                .'/edit'
        );

        self::assertResponseIsSuccessful();

        $client->submit(
            $crawler
                ->selectButton('Enregistrer')
                ->form([
                    'experiment[name]' =>
                        'Expérience modifiée',
                    'experiment[trafficPercentage]' =>
                        75,
                ])
        );

        self::assertResponseRedirects(
            '/admin/experiments/'
                .$experiment->getId()
                .'/edit'
        );

        $crawler = $client->request(
            'GET',
            '/admin/experiments/'
                .$experiment->getId()
                .'/variants/'
                .$variant->getId()
                .'/edit'
        );

        self::assertResponseIsSuccessful();

        $client->submit(
            $crawler
                ->selectButton('Enregistrer')
                ->form([
                    'experiment_variant[name]' =>
                        'Contrôle modifié',
                    'experiment_variant[weight]' =>
                        70,
                ])
        );

        self::assertResponseRedirects(
            '/admin/experiments/'
                .$experiment->getId()
                .'/edit'
        );

        $this->em()->clear();

        $persisted = $this
            ->em()
            ->getRepository(
                Experiment::class
            )
            ->find(
                $experiment->getId()
            );

        self::assertInstanceOf(
            Experiment::class,
            $persisted
        );

        self::assertSame(
            'Expérience modifiée',
            $persisted->getName()
        );

        self::assertSame(
            75,
            $persisted->getTrafficPercentage()
        );

        $persistedVariant =
            $this->em()
                ->getRepository(
                    ExperimentVariant::class
                )
                ->find(
                    $variant->getId()
                );

        self::assertInstanceOf(
            ExperimentVariant::class,
            $persistedVariant
        );

        self::assertSame(
            'Contrôle modifié',
            $persistedVariant->getName()
        );

        self::assertSame(
            70,
            $persistedVariant->getWeight()
        );
    }

    public function testStartedExperimentStructureIsLocked(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createManager()
        );

        $experiment =
            $this->createExperiment(
                'test-experiment-locked'
            );

        $variant =
            $this->createVariant(
                $experiment,
                'control',
                50
            );

        $experiment->setStatus(
            ExperimentStatus::Running
        );

        $this->em()->flush();

        $client->request(
            'GET',
            '/admin/experiments/'
                .$experiment->getId()
                .'/edit'
        );

        self::assertResponseRedirects(
            '/admin/experiments'
        );

        $client->request(
            'GET',
            '/admin/experiments/'
                .$experiment->getId()
                .'/variants/new'
        );

        self::assertResponseRedirects(
            '/admin/experiments'
        );

        $client->request(
            'GET',
            '/admin/experiments/'
                .$experiment->getId()
                .'/variants/'
                .$variant->getId()
                .'/edit'
        );

        self::assertResponseRedirects(
            '/admin/experiments'
        );

        $client->request(
            'POST',
            '/admin/experiments/'
                .$experiment->getId(),
            [
                '_token' => 'irrelevant',
            ]
        );

        self::assertResponseRedirects(
            '/admin/experiments'
        );

        $this->em()->clear();

        self::assertInstanceOf(
            Experiment::class,
            $this->em()
                ->getRepository(
                    Experiment::class
                )
                ->find(
                    $experiment->getId()
                )
        );
    }

    public function testDuplicateExperimentKeyShowsFormError(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createManager()
        );

        $this->createExperiment(
            'test-experiment-duplicate'
        );

        $crawler = $client->request(
            'GET',
            '/admin/experiments/new'
        );

        $client->submit(
            $crawler
                ->selectButton('Enregistrer')
                ->form([
                    'experiment[key]' =>
                        'test-experiment-duplicate',
                    'experiment[name]' =>
                        'Doublon',
                    'experiment[trafficPercentage]' =>
                        100,
                ])
        );

        self::assertResponseStatusCodeSame(
            422
        );

        self::assertSelectorTextContains(
            'body',
            'Cette clé d’expérience est déjà utilisée.'
        );
    }

    public function testExperimentRequiresTwoVariantsBeforeStart(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createManager()
        );

        $experiment =
            $this->createExperiment(
                'test-experiment-start-validation'
            );

        $this->createVariant(
            $experiment,
            'control',
            50
        );

        $crawler = $client->request(
            'GET',
            '/admin/experiments'
        );

        self::assertResponseIsSuccessful();

        $form = $crawler
            ->filter(
                sprintf(
                    'form[action="/admin/experiments/%d/start"]',
                    $experiment->getId()
                )
            )
            ->form();

        $client->submit(
            $form
        );

        self::assertResponseRedirects(
            '/admin/experiments'
        );

        $experimentId =
            $experiment->getId();

        $this->em()->clear();

        $persisted =
            $this->em()
                ->getRepository(
                    Experiment::class
                )
                ->find(
                    $experimentId
                );

        self::assertInstanceOf(
            Experiment::class,
            $persisted
        );

        self::assertSame(
            ExperimentStatus::Draft,
            $persisted->getStatus()
        );
    }

    public function testManagerCanRunCompleteExperimentLifecycle(): void
    {
        $client = static::createClient();

        $client->loginUser(
            $this->createManager()
        );

        $experiment =
            $this->createExperiment(
                'test-experiment-lifecycle'
            );

        $this->createVariant(
            $experiment,
            'control',
            50
        );

        $this->createVariant(
            $experiment,
            'candidate',
            50
        );

        $experimentId =
            $experiment->getId();

        foreach (
            [
                [
                    'action' => 'start',
                    'status' =>
                        ExperimentStatus::Running,
                ],
                [
                    'action' => 'pause',
                    'status' =>
                        ExperimentStatus::Paused,
                ],
                [
                    'action' => 'resume',
                    'status' =>
                        ExperimentStatus::Running,
                ],
                [
                    'action' => 'end',
                    'status' =>
                        ExperimentStatus::Ended,
                ],
            ] as $transition
        ) {
            $crawler = $client->request(
                'GET',
                '/admin/experiments'
            );

            self::assertResponseIsSuccessful();

            $form = $crawler
                ->filter(
                    sprintf(
                        'form[action="/admin/experiments/%d/%s"]',
                        $experimentId,
                        $transition['action']
                    )
                )
                ->form();

            $client->submit(
                $form
            );

            self::assertResponseRedirects(
                '/admin/experiments'
            );

            $this->em()->clear();

            $persisted =
                $this->em()
                    ->getRepository(
                        Experiment::class
                    )
                    ->find(
                        $experimentId
                    );

            self::assertInstanceOf(
                Experiment::class,
                $persisted
            );

            self::assertSame(
                $transition['status'],
                $persisted->getStatus()
            );
        }

        self::assertNotNull(
            $persisted->getStartsAt()
        );

        self::assertNotNull(
            $persisted->getEndsAt()
        );
    }

    private function createExperiment(
        string $key
    ): Experiment {
        $experiment = (new Experiment())
            ->setKey($key)
            ->setName(
                'Expérience '.$key
            )
            ->setTrafficPercentage(100);

        $this->em()->persist(
            $experiment
        );

        $this->em()->flush();

        return $experiment;
    }

    private function createVariant(
        Experiment $experiment,
        string $key,
        int $weight
    ): ExperimentVariant {
        $variant =
            (new ExperimentVariant())
                ->setKey($key)
                ->setName(
                    ucfirst($key)
                )
                ->setWeight($weight);

        $experiment->addVariant(
            $variant
        );

        $this->em()->persist(
            $variant
        );

        $this->em()->flush();

        return $variant;
    }

    private function createManager(): User
    {
        $user = (new User())
            ->setEmail(
                'experiment-manager-'
                .bin2hex(random_bytes(5))
                .'@shopwho.test'
            )
            ->setFirstName('Experiment')
            ->setLastName('Manager')
            ->setPassword('unused')
            ->setRoles([
                'ROLE_EXPERIMENT_MANAGER',
            ]);

        $this->em()->persist(
            $user
        );

        $this->em()->flush();

        return $user;
    }

    private function em():
        EntityManagerInterface
    {
        return static::getContainer()->get(
            EntityManagerInterface::class
        );
    }
}
