<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Enum\Gender;
use App\Factory\UserFactory;
use App\Story\AppStory;
use App\Tests\Functional\FunctionalTestCase;
use ReflectionClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Webmozart\Assert\Assert;

final class RegistrationControllerTest extends FunctionalTestCase
{
    public function testUserCanRegisterWithValidData(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Register now');
        self::assertSelectorExists('a[href="/app/en/login"]');

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'Axel',
            'registration_form[lastname]' => 'Schulz',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/app/en/register/check-email');
        UserFactory::assert()->count(1);
        UserFactory::assert()->empty(['isVerified' => true]);

        $user = UserFactory::first();
        self::assertSame('Axel', $user->getFirstname());
        self::assertSame('Schulz', $user->getLastname());
        self::assertSame('me@example.com', $user->getEmail());
        self::assertSame('en', $user->getLanguage());
        self::assertSame(Gender::Female, $user->getGender());

        self::assertQueuedEmailCount(1);
        $message = self::getSentEmail();
        self::assertEmailAddressContains($message, 'to', 'me@example.com');
        self::assertEmailAddressContains(
            $message,
            'from',
            'notifications@braunstetter.dev',
        );
        self::assertEmailHeaderSame($message, 'subject', 'One click to go');

        // Read the link before following the redirect: each request resets the collector.
        $verifyUrl = self::extractEmailUrl(
            self::getMailerMessageHtml(0),
            '/app/register/verify-email',
        );

        $this->client->followRedirect();
        self::assertRouteSame('app_register_check_email');

        $this->client->request(method: Request::METHOD_GET, uri: $verifyUrl);
        self::assertResponseRedirects('/app');

        $this->client->followRedirect();
        self::assertRouteSame('app_dashboard');

        $signedInUser = $this->getSignedInUser();
        self::assertInstanceOf(User::class, $signedInUser);
        self::assertSame($user->getId(), $signedInUser->getId());
        UserFactory::assert()->empty(['isVerified' => false]);
    }

    public function testUserReceivesNewEmailWhenConfirmationLinkExpires(): void
    {
        $user = UserFactory::new()
            ->unverified()
            ->create(['email' => 'wartet@example.com']);

        $expiredUrl = $this->generateExpiredSignedUrl($user);
        $this->client->request(method: Request::METHOD_GET, uri: $expiredUrl);

        // An expired link is not the visitor's fault, so a fresh one goes out.
        self::assertResponseRedirects('/app/en/register/check-email/resent');
        self::assertQueuedEmailCount(1);
        self::assertEmailAddressContains(
            self::getSentEmail(),
            'to',
            'wartet@example.com',
        );

        $verifyUrl = self::extractEmailUrl(
            self::getMailerMessageHtml(0),
            '/app/register/verify-email',
        );
        self::assertNotSame($expiredUrl, $verifyUrl);
        self::assertNull(
            $this->getSignedInUser(),
            'The dead link must not sign anybody in.',
        );

        $this->client->followRedirect();
        self::assertRouteSame('app_register_check_email_resent');

        $this->client->request(method: Request::METHOD_GET, uri: $verifyUrl);
        self::assertResponseRedirects('/app');

        $signedInUser = $this->getSignedInUser();
        self::assertInstanceOf(User::class, $signedInUser);
        self::assertSame($user->getId(), $signedInUser->getId());
        UserFactory::assert()->count(1);
        UserFactory::assert()->empty(['isVerified' => false]);
    }

    public function testUserCannotSignInTwiceWithTheSameConfirmationLink(): void
    {
        $user = UserFactory::new()
            ->unverified()
            ->create(['email' => 'zweimal@example.com']);

        $verifyUrl = $this->generateSignedUrl($user);
        $this->client->request(method: Request::METHOD_GET, uri: $verifyUrl);
        $this->client->request(method: Request::METHOD_GET, uri: '/app/logout');

        // The same link a second time: it is a password substitute until it expires,
        // and it sits in browser history, proxy logs and every forwarded mail.
        $this->client->request(method: Request::METHOD_GET, uri: $verifyUrl);

        self::assertResponseRedirects('/app/en/login');
        self::assertNull($this->getSignedInUser());

        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '#notifications p',
            'This address is already confirmed. Just sign in.',
        );
    }

    public function testUserCannotConfirmWithATamperedLink(): void
    {
        $user = UserFactory::new()
            ->unverified()
            ->create(['email' => 'manipuliert@example.com']);

        $tamperedUrl = preg_replace(
            pattern: '/signature=[^&]+/',
            replacement: 'signature=deadbeef',
            subject: $this->generateSignedUrl($user),
        );
        Assert::stringNotEmpty($tamperedUrl);

        $this->client->request(method: Request::METHOD_GET, uri: $tamperedUrl);

        self::assertResponseRedirects('/app/en/register');
        self::assertNull($this->getSignedInUser());
        UserFactory::assert()->empty(['isVerified' => true]);

        // Our own wording, not the bundle's: it names the next step instead of the
        // diagnosis, and it keeps the informal address in German too.
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '#notifications p',
            'This link does not work. Enter your address again',
        );

        $this->client->setServerParameter(
            key: 'HTTP_ACCEPT_LANGUAGE',
            value: 'de-DE,de;q=0.9,en;q=0.8',
        );
        $this->client->request(method: Request::METHOD_GET, uri: $tamperedUrl);
        $this->client->followRedirect();
        self::assertSelectorTextContains(
            '#notifications p',
            'Trag deine Adresse noch einmal ein',
        );
    }

    public function testUserCannotConfirmAnAccountThatIsGone(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/register/verify-email?id=999999',
        );

        // No account, nothing to say: back to the form, and no mail to anybody.
        self::assertResponseRedirects('/app/en/register');
        self::assertQueuedEmailCount(0);
        self::assertNull($this->getSignedInUser());
    }

    public function testUserCannotBeSwappedByAForeignConfirmationLink(): void
    {
        $signedIn = UserFactory::createOne(
            [
                'email' => 'angemeldet@example.com'],
        );
        Assert::isInstanceOf(value: $signedIn, class: User::class);
        $stranger = UserFactory::new()
            ->unverified()
            ->create(['email' => 'fremd@example.com']);

        $this->client->loginUser($signedIn);
        $this->client->request(
            method: Request::METHOD_GET,
            uri: $this->generateSignedUrl($stranger),
        );

        // The link confirms the stranger, but it must not take over the session.
        self::assertResponseRedirects('/app');
        $stillSignedIn = $this->getSignedInUser();
        self::assertInstanceOf(User::class, $stillSignedIn);
        self::assertSame($signedIn->getId(), $stillSignedIn->getId());
        self::assertTrue($stranger->getIsVerified());
    }

    public function testUserCannotLeaveFirstnameAndLastnameBlank(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => '',
            'registration_form[lastname]' => '',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->empty();
        self::assertQueuedEmailCount(0);

        self::assertSelectorTextContains(
            '#registration_form_firstname_error1',
            'Please enter your name.',
        );
        self::assertSelectorTextContains(
            '#registration_form_lastname_error1',
            'Please enter your name.',
        );
    }

    public function testUserCannotUseSingleCharacterForFirstnameAndLastname(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'A',
            'registration_form[lastname]' => 'S',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->empty();

        self::assertSelectorTextContains(
            '#registration_form_firstname_error1',
            'At least 2 characters, please.',
        );
        self::assertSelectorTextContains(
            '#registration_form_lastname_error1',
            'At least 2 characters, please.',
        );
    }

    public function testUserCannotLeaveEmailEmpty(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'Axel',
            'registration_form[lastname]' => 'Schulz',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => '',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->empty();

        self::assertSelectorTextContains(
            '#registration_form_email_error1',
            'Please enter your email address.',
        );
    }

    public function testUserMustAgreeToTerms(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'Axel',
            'registration_form[lastname]' => 'Schulz',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
        ]);

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->empty();

        self::assertSelectorTextContains(
            '#registration_form_agreeTerms_error1',
            'Please tick the box, then we can get going.',
        );
    }

    public function testUserCannotRegisterWithoutAGender(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        // A client can leave the select out entirely, so the server has to refuse.
        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'Axel',
            'registration_form[lastname]' => 'Schulz',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->empty();

        self::assertSelectorTextContains(
            '#registration_form_gender_error1',
            'Please make a choice here.',
        );
    }

    public function testUserMustProvideAPassword(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'Axel',
            'registration_form[lastname]' => 'Schulz',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => 'me@example.com',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->empty();

        self::assertSelectorTextContains(
            '#registration_form_plainPassword_first_error1',
            'Please come up with a password.',
        );
    }

    public function testUserCannotRegisterWithMismatchedPasswords(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'Axel',
            'registration_form[lastname]' => 'Schulz',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => 'Etwas-ganz-anderes-42',
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->empty();
        self::assertQueuedEmailCount(0);

        self::assertSelectorTextContains(
            '#registration_form_plainPassword_first_error1',
            'The two passwords are not the same.',
        );
    }

    public function testRegisteringATakenAddressFailsWithoutSayingWhy(): void
    {
        UserFactory::createOne(['email' => 'hat.schon@example.com']);

        $this->submitValidRegistration('hat.schon@example.com');

        self::assertResponseIsUnprocessable();
        UserFactory::assert()->count(1);

        // No mail, so typing a stranger's address into the form cannot reach them.
        self::assertQueuedEmailCount(0);

        // One generic box, the same one any other failure renders.
        self::assertSelectorExists('#registration-form_error');
        self::assertSelectorTextContains(
            '#registration-form_error .form-error',
            'There was a problem with your registration. Please try again later.',
        );
    }

    public function testRegisteringAnUnconfirmedAddressAgainAnswersTheSameWay(): void
    {
        UserFactory::new()
            ->unverified()
            ->create(['email' => 'wartet@example.com']);

        $this->submitValidRegistration('wartet@example.com');

        // Confirmed or not makes no difference here, and no second mail goes out.
        self::assertResponseIsUnprocessable();
        UserFactory::assert()->count(1);
        self::assertQueuedEmailCount(0);
    }

    public function testUserCanRegisterViaTheGermanPageAndGetsGermanAsAccountLanguage(): void
    {
        // The language rides in the path, so it survives submitForm().
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/de/register',
        );

        $this->client->submitForm(button: 'Registrieren', fieldValues: [
            'registration_form[firstname]' => 'Axel',
            'registration_form[lastname]' => 'Schulz',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => 'me@example.com',
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects('/app/de/register/check-email');
        self::assertSame('de', UserFactory::first()->getLanguage());

        // The account language decides, not the request: the worker never saw it.
        self::assertEmailHeaderSame(
            self::getSentEmail(),
            'subject',
            'Nur noch ein Klick',
        );
    }

    public function testUserCanSeeGermanContentOnTheGermanRegistrationPage(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/de/register',
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Language', 'de');
        self::assertPageTitleContains('Registrieren');
        self::assertSelectorTextContains('h2', 'Registrieren');
        self::assertSelectorTextContains(
            'label[for="registration_form_firstname"]',
            'Vorname',
        );
    }

    public function testUserCanSeeEnglishContentOnTheEnglishRegistrationPage(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
            server: [
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            ],
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Language', 'en');
        self::assertPageTitleContains('Register now');
        self::assertSelectorTextContains('h2', 'Register');
        self::assertSelectorTextContains(
            'label[for="registration_form_firstname"]',
            'First name',
        );
    }

    public function testUserCannotReachRegistrationWhenAlreadySignedIn(): void
    {
        // Psalm resolves Foundry's magic createOne() to mixed, hence the assert.
        $user = UserFactory::createOne();
        Assert::isInstanceOf(value: $user, class: User::class);
        $this->client->loginUser($user);

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        self::assertResponseRedirects('/app');
    }

    private function submitValidRegistration(string $email): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/en/register',
        );

        $this->client->submitForm(button: 'Register', fieldValues: [
            'registration_form[firstname]' => 'Probe',
            'registration_form[lastname]' => 'Nutzerin',
            'registration_form[gender]' => 'female',
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => AppStory::PASSWORD,
            'registration_form[plainPassword][second]' => AppStory::PASSWORD,
            'registration_form[agreeTerms]' => true,
        ]);
    }

    private function generateSignedUrl(
        User $user,
        ?int $lifetime = null,
    ): string {
        $helper = self::getContainer()->get(VerifyEmailHelperInterface::class);
        Assert::isInstanceOf(
            value: $helper,
            class: VerifyEmailHelperInterface::class,
        );
        Assert::notNull($user->getId());
        Assert::stringNotEmpty($user->getEmail());

        $property = new ReflectionClass($helper)
            ->getProperty('lifetime');
        $configuredLifetime = $property->getValue($helper);
        Assert::integer($configuredLifetime);

        if ($lifetime !== null) {
            $property->setValue(objectOrValue: $helper, value: $lifetime);
        }

        try {
            return $helper->generateSignature(
                routeName: 'app_register_verify_email',
                userId: (string) $user->getId(),
                userEmail: $user->getEmail(),
                extraParams: ['id' => $user->getId()],
            )
                ->getSignedUrl();
        } finally {
            $property->setValue(
                objectOrValue: $helper,
                value: $configuredLifetime,
            );
        }
    }

    /**
     * A link the way it looks after it sat in a mailbox for longer than its lifetime.
     */
    private function generateExpiredSignedUrl(User $user): string
    {
        return $this->generateSignedUrl(user: $user, lifetime: -86400);
    }

    private function getSignedInUser(): ?User
    {
        // The analysers disagree here: PHPStan types the test container's get() as
        // object and needs the assert, Psalm resolves it and calls the assert redundant.
        $security = self::getContainer()->get(Security::class);
        /** @psalm-suppress RedundantCondition */
        Assert::isInstanceOf(value: $security, class: Security::class);

        $user = $security->getUser();
        Assert::nullOrIsInstanceOf(value: $user, class: User::class);

        return $user;
    }
}
