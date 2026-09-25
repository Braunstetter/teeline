<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Exception\EmailAlreadyInUseException;
use App\Exception\InvalidEmailChangeTokenException;
use App\Factory\EmailChangeRequestFactory;
use App\Factory\UserFactory;
use App\Service\EmailChangeService;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\DatePoint;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class EmailChangeServiceTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private EmailChangeService $emailChangeService;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EmailChangeService $emailChangeService */
        $emailChangeService = self::getContainer()->get(
            EmailChangeService::class,
        );
        $this->emailChangeService = $emailChangeService;
    }

    public function testConfirmEmailChangeMovesTheAddressToTheAccount(): void
    {
        $user = UserFactory::createOne(['email' => 'old@example.com']);
        EmailChangeRequestFactory::createOne([
            'user' => $user,
            'newEmail' => 'new@example.com',
            'token' => 'valid-token',
            'expiresAt' => new DatePoint('+1 hour'),
        ]);

        $result = $this->emailChangeService->confirmEmailChange('valid-token');

        self::assertSame('new@example.com', $result->getEmail());
        UserFactory::assert()->exists(['email' => 'new@example.com']);
        EmailChangeRequestFactory::assert()->count(0);
    }

    public function testConfirmEmailChangeRejectsAnUnknownToken(): void
    {
        EmailChangeRequestFactory::createOne(['token' => 'real-token']);

        $this->expectException(InvalidEmailChangeTokenException::class);

        $this->emailChangeService->confirmEmailChange('unknown-token');
    }

    public function testConfirmEmailChangeRejectsAnExpiredToken(): void
    {
        EmailChangeRequestFactory::createOne([
            'token' => 'expired-token',
            'expiresAt' => new DatePoint('-1 hour'),
        ]);

        $this->expectException(InvalidEmailChangeTokenException::class);

        $this->emailChangeService->confirmEmailChange('expired-token');
    }

    public function testConfirmEmailChangeReportsWhenTheAddressWasTakenMeanwhile(): void
    {
        $user = UserFactory::createOne(['email' => 'old@example.com']);
        UserFactory::createOne(['email' => 'taken@example.com']);
        EmailChangeRequestFactory::createOne([
            'user' => $user,
            'newEmail' => 'taken@example.com',
            'token' => 'valid-token',
            'expiresAt' => new DatePoint('+1 hour'),
        ]);

        $this->expectException(EmailAlreadyInUseException::class);

        $this->emailChangeService->confirmEmailChange('valid-token');
    }
}
