<?php

namespace App\Tests;

use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class ReviewTest extends KernelTestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testRatingBelowOneIsRejected(): void
    {
        self::assertGreaterThan(0, $this->validator()->validate($this->review(0))->count());
    }

    public function testRatingAboveFiveIsRejected(): void
    {
        self::assertGreaterThan(0, $this->validator()->validate($this->review(6))->count());
    }

    public function testBlankCommentIsNormalizedToNull(): void
    {
        $review = $this->review(4)->setComment("  \n ");

        self::assertNull($review->getComment());
    }

    public function testPublicDisplayNameNeverFallsBackToEmail(): void
    {
        $anonymousName = (new User())->setEmail('private@example.test');
        $named = (new User())->setEmail('other@example.test')->setFirstName('Alice')->setLastName('Martin');

        self::assertSame('Client Shopwho', $anonymousName->getPublicDisplayName());
        self::assertSame('Alice M.', $named->getPublicDisplayName());
    }

    private function review(int $rating): Review
    {
        return new Review(new User(), new Product(), $rating);
    }

    private function validator(): ValidatorInterface
    {
        self::bootKernel();
        return static::getContainer()->get(ValidatorInterface::class);
    }
}
