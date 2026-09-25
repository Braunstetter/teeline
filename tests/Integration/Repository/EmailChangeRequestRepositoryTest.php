<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\EmailChangeRequest;
use App\Entity\User;
use App\Factory\EmailChangeRequestFactory;
use App\Factory\UserFactory;
use App\Repository\EmailChangeRequestRepository;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Webmozart\Assert\Assert;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class EmailChangeRequestRepositoryTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    private EmailChangeRequestRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EmailChangeRequestRepository $repository */
        $repository = self::getContainer()->get(
            EmailChangeRequestRepository::class,
        );
        $this->repository = $repository;
    }

    // -- findValidByToken --

    public function testFindValidByTokenReturnsRequestWithValidToken(): void
    {
        $request = EmailChangeRequestFactory::createOne([
            'token' => 'valid-token',
            'expiresAt' => new DatePoint('+1 hour'),
        ]);
        Assert::isInstanceOf(value: $request, class: EmailChangeRequest::class);

        $result = $this->repository->findValidByToken('valid-token');

        self::assertInstanceOf(EmailChangeRequest::class, $result);
        self::assertSame($request->getId(), $result->getId());
    }

    public function testFindValidByTokenReturnsNullForUnknownToken(): void
    {
        EmailChangeRequestFactory::createOne([
            'token' => 'existing-token',
        ]);

        self::assertNotInstanceOf(
            EmailChangeRequest::class,
            $this->repository->findValidByToken('unknown-token'),
        );
    }

    public function testFindValidByTokenReturnsNullForExpiredRequest(): void
    {
        self::mockTime('2026-03-01 10:00');

        EmailChangeRequestFactory::createOne([
            'token' => 'expired-token',
            'expiresAt' => new DatePoint('2026-03-01 09:00'),
        ]);

        self::assertNotInstanceOf(
            EmailChangeRequest::class,
            $this->repository->findValidByToken('expired-token'),
        );
    }

    // -- removeAllForUser --

    public function testRemoveAllForUserDeletesAllRequestsForUser(): void
    {
        $user = UserFactory::createOne();
        Assert::isInstanceOf(value: $user, class: User::class);
        EmailChangeRequestFactory::createMany(number: 3, attributes: [
            'user' => $user,
        ]);

        $this->repository->removeAllForUser($user);

        EmailChangeRequestFactory::assert()->count(0);
    }

    public function testRemoveAllForUserDoesNotAffectOtherUsers(): void
    {
        $user = UserFactory::createOne();
        Assert::isInstanceOf(value: $user, class: User::class);
        $otherUser = UserFactory::createOne();

        EmailChangeRequestFactory::createOne([
            'user' => $user,
        ]);
        EmailChangeRequestFactory::createOne([
            'user' => $otherUser,
        ]);

        $this->repository->removeAllForUser($user);

        EmailChangeRequestFactory::assert()->count(1);
        EmailChangeRequestFactory::assert()->exists([
            'user' => $otherUser,
        ]);
    }
}
