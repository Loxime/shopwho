<?php

namespace App\Tests;

use App\Entity\Experiment;
use App\Entity\ExperimentVariant;
use App\Enum\ExperimentStatus;
use App\Service\ExperimentAssignmentService;
use App\Service\TrackingIdentityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class ExperimentAssignmentServiceTest extends TestCase
{
    public function testSameVisitorKeepsSameVariant(): void
    {
        $experiment = $this->experiment();

        $service = $this->service();

        $first = $service->assignForVisitor(
            $experiment,
            'visitor-stable'
        );

        $second = $service->assignForVisitor(
            $experiment,
            'visitor-stable'
        );

        self::assertNotNull($first);
        self::assertSame($first, $second);
    }

    public function testAssignmentDoesNotDependOnVariantInsertionOrder(): void
    {
        $firstExperiment = $this->experiment(
            reverseVariants: false
        );

        $secondExperiment = $this->experiment(
            reverseVariants: true
        );

        $service = $this->service();

        for ($index = 0; $index < 100; ++$index) {
            $visitorId = 'visitor-'.$index;

            $first = $service->assignForVisitor(
                $firstExperiment,
                $visitorId
            );

            $second = $service->assignForVisitor(
                $secondExperiment,
                $visitorId
            );

            self::assertNotNull($first);
            self::assertNotNull($second);

            self::assertSame(
                $first->getKey(),
                $second->getKey()
            );
        }
    }

    public function testInactiveExperimentDoesNotAssignVariant(): void
    {
        $experiment = $this->experiment();

        $experiment->setStatus(
            ExperimentStatus::Paused
        );

        self::assertNull(
            $this->service()->assignForVisitor(
                $experiment,
                'visitor-paused'
            )
        );
    }

    public function testZeroTrafficDoesNotAssignVariant(): void
    {
        $experiment = $this->experiment();

        $experiment->setTrafficPercentage(0);

        self::assertNull(
            $this->service()->assignForVisitor(
                $experiment,
                'visitor-zero-traffic'
            )
        );
    }

    public function testExperimentOutsideDateWindowDoesNotAssignVariant(): void
    {
        $now = new \DateTimeImmutable(
            '2026-09-10 12:00:00'
        );

        $experiment = $this->experiment();

        $experiment
            ->setStartsAt(
                new \DateTimeImmutable(
                    '2026-09-11 12:00:00'
                )
            );

        self::assertNull(
            $this->service()->assignForVisitor(
                $experiment,
                'visitor-future',
                $now
            )
        );
    }

    public function testExperimentRequiresAtLeastTwoValidVariants(): void
    {
        $experiment = (new Experiment())
            ->setKey('single-variant')
            ->setName('Single variant')
            ->setStatus(
                ExperimentStatus::Running
            );

        $experiment->addVariant(
            (new ExperimentVariant())
                ->setKey('control')
                ->setName('Control')
                ->setWeight(1)
        );

        self::assertNull(
            $this->service()->assignForVisitor(
                $experiment,
                'visitor-single'
            )
        );
    }

    public function testWeightedExperimentUsesBothVariants(): void
    {
        $experiment = $this->experiment();

        $service = $this->service();

        $seen = [];

        for ($index = 0; $index < 200; ++$index) {
            $variant =
                $service->assignForVisitor(
                    $experiment,
                    'visitor-'.$index
                );

            self::assertNotNull($variant);

            $seen[$variant->getKey()] = true;
        }

        self::assertArrayHasKey(
            'control',
            $seen
        );

        self::assertArrayHasKey(
            'candidate',
            $seen
        );
    }

    public function testTrafficPercentageMustStayWithinBounds(): void
    {
        $experiment = new Experiment();

        $this->expectException(
            \OutOfRangeException::class
        );

        $experiment->setTrafficPercentage(101);
    }

    public function testVariantWeightMustBePositive(): void
    {
        $variant = new ExperimentVariant();

        $this->expectException(
            \OutOfRangeException::class
        );

        $variant->setWeight(0);
    }

    private function service(): ExperimentAssignmentService
    {
        return new ExperimentAssignmentService(
            new RequestStack(),
            new TrackingIdentityService()
        );
    }

    private function experiment(
        bool $reverseVariants = false
    ): Experiment {
        $experiment = (new Experiment())
            ->setKey('homepage-recommendation-strategy')
            ->setName(
                'Homepage recommendation strategy'
            )
            ->setStatus(
                ExperimentStatus::Running
            )
            ->setTrafficPercentage(100);

        $control = (new ExperimentVariant())
            ->setKey('control')
            ->setName('Control')
            ->setWeight(50);

        $candidate = (new ExperimentVariant())
            ->setKey('candidate')
            ->setName('Candidate')
            ->setWeight(50);

        if ($reverseVariants) {
            $experiment
                ->addVariant($candidate)
                ->addVariant($control);
        } else {
            $experiment
                ->addVariant($control)
                ->addVariant($candidate);
        }

        return $experiment;
    }
}
