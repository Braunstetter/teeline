<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Webmozart\Assert\Assert;

final class WebsiteControllerTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function authPageProvider(): iterable
    {
        yield 'login' => ['/app/en/login'];
        yield 'register' => ['/app/en/register'];
        yield 'register check-email' => ['/app/en/register/check-email'];
        yield 'reset-password request' => ['/app/en/reset-password'];
        yield 'reset-password check-email' => [
            '/app/en/reset-password/check-email',
        ];
        yield 'profile delete success' => ['/en/profile/delete/success'];
    }

    #[DataProvider('authPageProvider')]
    public function testAuthPagesSendNoindex(string $url): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(
            'meta[name="robots"][content="noindex, nofollow"]',
        );
    }

    public function testResetPasswordFormSendsNoindex(): void
    {
        // The reset form only renders behind a valid token, so one is minted
        // directly -- the mail round trip is ResetPasswordControllerTest's job.
        $user = UserFactory::createOne();
        Assert::isInstanceOf(value: $user, class: User::class);
        $helper = self::getContainer()->get(
            ResetPasswordHelperInterface::class,
        );
        Assert::isInstanceOf(
            value: $helper,
            class: ResetPasswordHelperInterface::class,
        );
        $token = $helper->generateResetToken($user)
            ->getToken();

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/reset-password/reset/' . $token,
        );
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(
            'meta[name="robots"][content="noindex, nofollow"]',
        );
    }
}
