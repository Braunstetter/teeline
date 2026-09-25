<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

/**
 * The language a user picked in their profile has to reach the interface, not just
 * their mail. Nothing under /app carries a {_locale} segment, so without the listener
 * the only source left is the Accept-Language header -- which is the browser's
 * language, not the account's.
 */
final class UserLocaleListenerTest extends FunctionalTestCase
{
    public function testDashboardFollowsTheUserLanguageRatherThanTheBrowser(): void
    {
        $user = $this->createUserSpeaking('de');

        $this->client->loginUser($user);
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app',
            server: ['HTTP_ACCEPT_LANGUAGE' => 'en'],
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Willkommen zurück');
    }

    /**
     * The assertion every "did the language change" test gets wrong: the English
     * headline is 'Profile' and the German one 'Profil', so assertPageTitleContains()
     * on the German string passes in both languages. The submit button does not
     * overlap.
     */
    public function testProfileIsRenderedInTheNewLanguageRightAfterTheChange(): void
    {
        $user = $this->createUserSpeaking('en');

        $this->client->loginUser($user);
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile',
        );
        self::assertSelectorTextContains('button[type=submit]', 'Save');

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[language]' => 'de',
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('button[type=submit]', 'Speichern');
    }

    /**
     * Two passes through one kernel, which is how the app runs under FrankenPHP:
     * a locale the listener set for one account must not still be there for the next.
     * With a fresh kernel per request this bug cannot be seen at all.
     */
    public function testTheLocaleDoesNotSurviveIntoTheNextRequest(): void
    {
        $german = $this->createUserSpeaking('de');
        $english = $this->createUserSpeaking('en');

        $this->client->disableReboot();

        $this->client->loginUser($german);
        $this->client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertSelectorTextContains('body', 'Willkommen zurück');

        $this->client->loginUser($english);
        $this->client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertSelectorTextContains('body', 'Welcome back');
    }

    private function createUserSpeaking(string $language): User
    {
        $user = UserFactory::createOne([
            'language' => $language,
        ]);
        Assert::isInstanceOf(value: $user, class: User::class);

        return $user;
    }
}
