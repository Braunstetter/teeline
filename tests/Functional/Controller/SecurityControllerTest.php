<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Story\AppStory;
use App\Tests\Functional\FunctionalTestCase;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class SecurityControllerTest extends FunctionalTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Throttling counts live in the cache, which outlives the database reset:
        // without this, a rerun within the minute starts throttled. Clearing the pool
        // rather than single keys -- those are HMACs of username and client IP, and
        // the IP one is never reset by a successful login.
        $rateLimiterCache = self::getContainer()->get('cache.rate_limiter');
        Assert::isInstanceOf(
            value: $rateLimiterCache,
            class: CacheItemPoolInterface::class,
        );
        $rateLimiterCache->clear();
    }

    public function testUserCanReachTheLoginPageWithoutAnAccount(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/login',
        );

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Login');
        self::assertSelectorTextContains('h2', 'Login');
    }

    public function testUserCanSignInWithValidCredentials(): void
    {
        UserFactory::createOne([
            'email' => 'wer@example.com',
            'password' => AppStory::PASSWORD,
        ]);

        $this->submitLogin(
            email: 'wer@example.com',
            password: AppStory::PASSWORD,
        );

        self::assertResponseRedirects('/app');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertRouteSame('app_dashboard');
    }

    public function testUserCannotTellAnUnknownAddressFromAWrongPassword(): void
    {
        UserFactory::createOne([
            'email' => 'wer@example.com',
            'password' => AppStory::PASSWORD,
        ]);

        // Nothing here may reveal whether the address exists.
        $this->submitLogin(
            email: 'kennt.keiner@example.com',
            password: AppStory::PASSWORD,
        );
        self::assertResponseRedirects('/app/en/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            'form p',
            'Invalid credentials.',
        );

        $this->submitLogin(
            email: 'wer@example.com',
            password: 'falsch-falsch-falsch',
        );
        self::assertResponseRedirects('/app/en/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            'form p',
            'Invalid credentials.',
        );
    }

    public function testUserCannotSignInWithAnUnconfirmedAccount(): void
    {
        UserFactory::new()
            ->unverified()
            ->create([
                'email' => 'unbestaetigt@example.com',
                'password' => AppStory::PASSWORD,
            ]);

        $this->submitLogin(
            email: 'unbestaetigt@example.com',
            password: AppStory::PASSWORD,
        );

        // The user provider does not find it, so the answer is the one a wrong
        // password gets -- confirmed or not stays invisible here too.
        self::assertResponseRedirects('/app/en/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            'form p',
            'Invalid credentials.',
        );
    }

    public function testUserCanSeeTheLastUsernameAfterAFailedSignIn(): void
    {
        $this->submitLogin(
            email: 'wer@example.com',
            password: 'falsch-falsch-falsch',
        );
        $this->client->followRedirect();

        self::assertSame(
            'wer@example.com',
            $this->client->getCrawler()
                ->filter('#login_form__username')
                ->attr('value'),
        );
    }

    public function testUserCannotReachTheLoginPageWhenAlreadySignedIn(): void
    {
        $this->client->loginUser($this->createSignedInUser());

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/login',
        );

        self::assertResponseRedirects('/app');
    }

    public function testUserCannotSignInAfterTooManyFailedAttempts(): void
    {
        UserFactory::createOne([
            'email' => 'zuviel@example.com',
            'password' => AppStory::PASSWORD,
        ]);

        // The login page is reachable from the internet, so guard it against guessing.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->submitLogin(
                email: 'zuviel@example.com',
                password: 'falsch-falsch-falsch',
            );
        }

        // The sixth attempt is refused even though the password is now correct.
        $this->submitLogin(
            email: 'zuviel@example.com',
            password: AppStory::PASSWORD,
        );

        self::assertResponseRedirects('/app/en/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            'form p',
            'Too many failed login attempts',
        );
    }

    public function testUserCanSignOut(): void
    {
        $this->client->loginUser($this->createSignedInUser());

        $this->client->request(method: Request::METHOD_GET, uri: '/app/logout');

        self::assertResponseRedirects('/app/en/login');
        $this->client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertResponseRedirects('/app/en/login');
    }

    public function testUserCanNavigateToTheRegistrationPage(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/login',
        );
        $this->client->clickLink('Register now');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('app_register');
    }

    public function testUserCanSeeGermanContentOnTheGermanLoginPage(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/de/login',
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Language', 'de');
        self::assertPageTitleContains('Anmelden');
        self::assertSelectorTextContains('h2', 'Anmelden');
        self::assertSame('de', $this->getDocumentLanguage());
    }

    public function testUserCanSeeEnglishContentOnTheEnglishLoginPage(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/login',
            server: [
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Language', 'en');
        self::assertSame('en', $this->getDocumentLanguage());
    }

    /**
     * Psalm resolves Foundry's magic createOne() to mixed, hence the assert.
     */
    private function createSignedInUser(): User
    {
        $user = UserFactory::createOne();
        Assert::isInstanceOf(value: $user, class: User::class);

        return $user;
    }

    private function getDocumentLanguage(): ?string
    {
        return $this->client->getCrawler()
            ->filter('html')
            ->attr('lang');
    }

    private function submitLogin(string $email, string $password): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/login',
        );

        $this->client->submitForm(button: 'Sign in', fieldValues: [
            'login_form[_username]' => $email,
            'login_form[_password]' => $password,
        ]);
    }
}
