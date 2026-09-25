<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Factory\UserFactory;
use App\Story\AppStory;
use App\Tests\Functional\FunctionalTestCase;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Webmozart\Assert\Assert;

final class ResetPasswordControllerTest extends FunctionalTestCase
{
    private const string NEW_PASSWORD = 'Ganz-neues-Passwort-77';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // The reset limiter counts per client and lives in the cache, which outlives the
        // database reset -- a rerun within the window would start throttled.
        $cache = self::getContainer()->get('cache.rate_limiter');
        Assert::isInstanceOf(
            value: $cache,
            class: CacheItemPoolInterface::class,
        );
        $cache->clear();
    }

    public function testUserCanResetTheirPassword(): void
    {
        UserFactory::createOne([
            'email' => 'me@example.com',
            'password' => AppStory::PASSWORD,
        ]);

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/reset-password',
        );
        self::assertResponseIsSuccessful();

        $this->client->submitForm(
            button: 'Send password reset email',
            fieldValues: [
                'reset_password_request_form[email]' => 'me@example.com',
            ],
        );

        self::assertResponseRedirects('/app/en/reset-password/check-email');
        self::assertQueuedEmailCount(1);
        $message = self::getSentEmail();
        self::assertEmailAddressContains($message, 'to', 'me@example.com');
        self::assertEmailAddressContains(
            $message,
            'from',
            'notifications@braunstetter.dev',
        );

        // The subject comes from a translation key: a wrong one would ship the key
        // itself, and nobody reads their own outgoing mail.
        self::assertEmailHeaderSame($message, 'subject', 'Set a new password');

        // Read the link before following the redirect: each request resets the collector.
        $resetUrl = self::extractEmailUrl(
            self::getMailerMessageHtml(0),
            '/app/en/reset-password/reset/',
        );

        $this->client->followRedirect();
        self::assertRouteSame('app_reset_password_check_email');

        // The token moves into the session, so the URL it lands on carries none.
        $this->client->request(method: Request::METHOD_GET, uri: $resetUrl);
        self::assertResponseRedirects('/app/en/reset-password/reset');
        $this->client->followRedirect();

        $this->client->submitForm(button: 'Reset password', fieldValues: [
            'change_password_form[plainPassword][first]' => self::NEW_PASSWORD,
            'change_password_form[plainPassword][second]' => self::NEW_PASSWORD,
        ]);

        // Signed in straight away: whoever opened the link proved they hold the mailbox.
        // The redirect alone proves nothing -- signed out, /app bounces to the login form.
        self::assertResponseRedirects('/app');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertRouteSame('app_dashboard');

        $user = UserFactory::find(['email' => 'me@example.com']);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        Assert::isInstanceOf(
            value: $hasher,
            class: UserPasswordHasherInterface::class,
        );

        self::assertTrue(
            $hasher->isPasswordValid(
                user: $user,
                plainPassword: self::NEW_PASSWORD,
            ),
        );
        self::assertFalse(
            $hasher->isPasswordValid(
                user: $user,
                plainPassword: AppStory::PASSWORD,
            ),
        );
    }

    public function testUserCannotUseTheSameLinkTwice(): void
    {
        UserFactory::createOne([
            'email' => 'me@example.com',
            'password' => AppStory::PASSWORD,
        ]);

        $resetUrl = $this->requestResetLinkFor('me@example.com');

        $this->client->request(method: Request::METHOD_GET, uri: $resetUrl);
        $this->client->followRedirect();
        $this->client->submitForm(button: 'Reset password', fieldValues: [
            'change_password_form[plainPassword][first]' => self::NEW_PASSWORD,
            'change_password_form[plainPassword][second]' => self::NEW_PASSWORD,
        ]);
        self::assertResponseRedirects('/app');

        // The same mail again: a link that still worked would be a standing back door
        // for whoever ever got hold of that message.
        $this->client->request(method: Request::METHOD_GET, uri: '/app/logout');
        $this->client->request(method: Request::METHOD_GET, uri: $resetUrl);

        self::assertResponseRedirects('/app/en/reset-password/reset');
        $this->client->followRedirect();
        self::assertResponseRedirects('/app/en/reset-password');

        $this->client->followRedirect();
        self::assertSelectorExists('#notifications p');
    }

    public function testUserCannotResetWithATamperedToken(): void
    {
        UserFactory::createOne(['email' => 'me@example.com']);

        $resetUrl = $this->requestResetLinkFor('me@example.com');
        $tampered = substr(string: $resetUrl, offset: 0, length: -4) . 'dead';

        // Driven in German: the bundle's own catalogue addresses people formally,
        // which this product does not, so we override it. The override is keyed by
        // the bundle's English source string -- if a release ever reworded it, the
        // override would stop matching and the formal text would quietly return.
        $tampered = str_replace(
            search: '/app/en/',
            replace: '/app/de/',
            subject: $tampered,
        );

        $this->client->request(method: Request::METHOD_GET, uri: $tampered);
        $this->client->followRedirect();

        // Back to the form, and no way to tell a wrong token from an expired one.
        self::assertResponseRedirects('/app/de/reset-password');
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/de/reset-password',
        );
        self::assertSelectorTextSame(
            '#notifications p',
            'Der Link stimmt nicht. Fordere bitte einen neuen an.',
        );
    }

    public function testUserCannotTellWhetherTheAddressExists(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/reset-password',
        );

        $this->client->submitForm(
            button: 'Send password reset email',
            fieldValues: [
                'reset_password_request_form[email]' => 'kennt.keiner@example.com',
            ],
        );

        // Same page as for an address that exists, and nothing sent.
        self::assertResponseRedirects('/app/en/reset-password/check-email');
        self::assertQueuedEmailCount(0);

        $this->client->followRedirect();
        self::assertSelectorTextContains('h2', 'Password Reset Email Sent');
    }

    public function testAnUnconfirmedAccountGetsNoResetMail(): void
    {
        UserFactory::new()
            ->unverified()
            ->create(['email' => 'unbestaetigt@example.com']);

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/reset-password',
        );

        $this->client->submitForm(
            button: 'Send password reset email',
            fieldValues: [
                'reset_password_request_form[email]' => 'unbestaetigt@example.com',
            ],
        );

        // Invisible here as it is at the login form, and told apart by nothing.
        self::assertResponseRedirects('/app/en/reset-password/check-email');
        self::assertQueuedEmailCount(0);
    }

    public function testUserCannotRequestAResetTooOften(): void
    {
        UserFactory::createOne(['email' => 'me@example.com']);

        // The limiter lives in the cache, which a kernel reboot would drop.
        $this->client->disableReboot();

        $limiterFactory = self::getContainer()->get('limiter.password_reset');
        Assert::isInstanceOf(
            value: $limiterFactory,
            class: RateLimiterFactoryInterface::class,
        );
        $limiterFactory->create('127.0.0.1')
            ->consume(3);

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/reset-password',
        );
        $this->client->submitForm(
            button: 'Send password reset email',
            fieldValues: [
                'reset_password_request_form[email]' => 'me@example.com',
            ],
        );

        // Throttled silently: the same page, but no mail.
        self::assertResponseRedirects('/app/en/reset-password/check-email');
        self::assertQueuedEmailCount(0);
    }

    private function requestResetLinkFor(string $email): string
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/reset-password',
        );
        $this->client->submitForm(
            button: 'Send password reset email',
            fieldValues: [
                'reset_password_request_form[email]' => $email,
            ],
        );

        return self::extractEmailUrl(
            self::getMailerMessageHtml(0),
            '/app/en/reset-password/reset/',
        );
    }
}
