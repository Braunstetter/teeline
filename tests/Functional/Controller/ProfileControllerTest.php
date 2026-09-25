<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\EmailChangeRequest;
use App\Entity\Upload\Upload;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Factory\EmailChangeRequestFactory;
use App\Factory\UploadFactory;
use App\Factory\UserFactory;
use App\Story\AppStory;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Trait\UploadTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Uid\Ulid;
use Webmozart\Assert\Assert;

final class ProfileControllerTest extends FunctionalTestCase
{
    use UploadTrait;

    private string $path = '/app/profile';

    public function testUserCanAccessProfileDeleteSuccessPage(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/en/profile/delete/success',
        );

        self::assertResponseIsSuccessful();
    }

    public function testUserCannotAccessProfilePageWhenUnauthenticated(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile',
        );

        self::assertResponseRedirects('/app/en/login');
    }

    public function testUserIsRedirectedToProfileAfterLogin(): void
    {
        // Try to access profile while not authenticated
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile',
        );

        // Should redirect to login
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Should be on login page
        self::assertRouteSame('app_login');

        // Create a user and login
        UserFactory::createOne([
            'email' => 'test@example.com',
            'password' => AppStory::PASSWORD,
        ]);

        // Submit login form
        $this->client->submitForm(button: 'Sign in', fieldValues: [
            'login_form[_username]' => 'test@example.com',
            'login_form[_password]' => AppStory::PASSWORD,
        ]);

        // Should redirect back to profile page, not to the dashboard
        self::assertResponseRedirects('/app/profile');
        $this->client->followRedirect();

        // Should be on profile page
        self::assertRouteSame('app_profile');
        self::assertSelectorExists('form'); // Profile page should have a form
    }

    public function testUserCannotDeleteAccountWhenUnauthenticated(): void
    {
        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/app/profile/delete',
        );

        self::assertResponseRedirects('/app/en/login');
    }

    public function testUserCanAccessProfilePage(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Profile');
        self::assertSelectorExists('#profile-delete-btn');
    }

    public function testUserCanDeleteTheirAccount(): void
    {
        $user = $this->createUser();
        EmailChangeRequestFactory::createOne([
            'user' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $this->client->submitForm('profile-delete-btn');
        self::assertResponseRedirects('/en/profile/delete/success');

        UserFactory::assert()->empty();
        EmailChangeRequestFactory::assert()->empty();
    }

    public function testAccountDeletionDoesNotTouchOtherUsersData(): void
    {
        $user = $this->createUser();
        EmailChangeRequestFactory::createOne([
            'user' => $user,
        ]);

        // Another user with the same web of dependants: none of it may go.
        $anotherUser = $this->createUserWithPicture();
        $anotherUserRequest = EmailChangeRequestFactory::createOne([
            'user' => $anotherUser,
        ]);
        Assert::isInstanceOf(
            value: $anotherUserRequest,
            class: EmailChangeRequest::class,
        );
        $anotherUserPicture = $anotherUser->getPicture();
        Assert::isInstanceOf(value: $anotherUserPicture, class: Upload::class);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $this->client->submitForm('profile-delete-btn');
        self::assertResponseRedirects('/en/profile/delete/success');

        // Exactly one of each left -- and it is the other user's. The count
        // catches leftovers an exists/notExists pair would never see.
        UserFactory::assert()->count(1);
        EmailChangeRequestFactory::assert()->count(1);
        UploadFactory::assert()->count(1);

        UserFactory::assert()->exists([
            'id' => $anotherUser->getId(),
        ]);
        EmailChangeRequestFactory::assert()->exists([
            'id' => $anotherUserRequest->getId(),
        ]);
        UploadFactory::assert()->exists([
            'id' => $anotherUserPicture->getId(),
        ]);
    }

    public function testUserCannotDeleteOtherUsersAccount(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        // Login as user2 and get their CSRF token
        $this->client->loginUser($user2);
        $crawler = $this->client->request(
            method: Request::METHOD_GET,
            uri: $this->path,
        );
        self::assertResponseIsSuccessful();

        // Extract CSRF token from user2's delete form
        $deleteForm = $crawler->selectButton('profile-delete-btn')
            ->form();
        $tokenField = $deleteForm->get('_token');
        Assert::isInstanceOf(value: $tokenField, class: FormField::class);
        $csrfToken = $tokenField->getValue();
        Assert::string($csrfToken);

        // Now login as user1 and try to delete using user2's CSRF token
        $this->client->restart(); // Clear session before switching users
        $this->client->loginUser($user1);

        // Try to use user2's CSRF token to delete user2's account
        // This should fail because CSRF tokens are user-specific
        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/app/profile/delete',
            parameters: ['_token' => $csrfToken],
        );

        // Should redirect back to profile (invalid token) - user2 should still exist
        self::assertResponseRedirects('/app/profile');

        // User2 should still exist
        UserFactory::assert()->exists([
            'id' => $user2->getId(),
        ]);
    }

    public function testUserCanChangeLanguageInProfile(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertSelectorExists('#profile-delete-btn');
        self::assertPageTitleContains('Profile');

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[language]' => 'de',
        ]);

        self::assertResponseRedirects($this->path);
        UserFactory::assert()->exists([
            'id' => $user->getId(),
            'language' => 'de',
        ]);

        $this->client->followRedirect();
        self::assertPageTitleContains('Profil');
    }

    public function testUserCanUploadProfilePicture(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $form = $this->client->getCrawler()
            ->selectButton('Save')
            ->form();

        [$values, $files] = $this->addUploadToForm(
            form: $form,
            filePath: 'assets/images/fixtures/avatar.png',
            field: '[profile_form][picture][file]',
        );

        $this->client->request(
            method: $form->getMethod(),
            uri: $form->getUri(),
            parameters: $values,
            files: $files,
        );

        self::assertResponseRedirects($this->path);

        $picture = $this->reload($user)
            ->getPicture();
        Assert::isInstanceOf(value: $picture, class: Upload::class);

        self::assertSame('avatar.png', $picture->getOriginalFilename());
        self::assertSame('image/png', $picture->getMimeType());
        self::assertSame('200x200', $picture->dimensions());
        self::assertSame(10808, $picture->size());
        self::assertNotInstanceOf(File::class, $picture->getFile());
        self::assertInstanceOf(Ulid::class, $picture->getId());

        $this->client->followRedirect();
        self::assertPageTitleContains('Profile');
    }

    public function testUserCanChangeExistingProfilePicture(): void
    {
        $user = $this->createUserWithPicture();
        $previous = $user->getPicture();
        Assert::isInstanceOf(value: $previous, class: Upload::class);
        $previousFilename = $previous->getFilename();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $form = $this->client->getCrawler()
            ->selectButton('Save')
            ->form();

        [$values, $files] = $this->addUploadToForm(
            form: $form,
            filePath: 'assets/images/fixtures/puppy-1.jpg',
            field: '[profile_form][picture][file]',
        );

        $this->client->request(
            method: $form->getMethod(),
            uri: $form->getUri(),
            parameters: $values,
            files: $files,
        );

        self::assertResponseRedirects($this->path);

        $picture = $this->reload($user)
            ->getPicture();
        Assert::isInstanceOf(value: $picture, class: Upload::class);

        self::assertSame('puppy-1.jpg', $picture->getOriginalFilename());
        self::assertSame('image/jpeg', $picture->getMimeType());
        self::assertNotSame($previousFilename, $picture->getFilename());
    }

    public function testUserCanRemoveProfilePicture(): void
    {
        $user = $this->createUserWithPicture();
        $userPicture = $user->getPicture();
        Assert::isInstanceOf(value: $userPicture, class: Upload::class);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[picture][id]' => '',
        ]);

        self::assertResponseRedirects($this->path);
        self::assertNull($this->reload($user)->getPicture());

        $this->client->followRedirect();
        self::assertPageTitleContains('Profile');
        UploadFactory::assert()->notExists([
            'id' => $userPicture->getId(),
        ]);
    }

    public function testUserCanDeleteAccountAndProfilePictureIsRemoved(): void
    {
        $user = $this->createUserWithPicture();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $this->client->submitForm('profile-delete-btn');
        self::assertResponseRedirects('/en/profile/delete/success');

        UserFactory::assert()->empty();
        UploadFactory::assert()->empty();
    }

    public function testUserCannotLeaveFirstnameAndLastnameBlank(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[firstname]' => '',
            'profile_form[lastname]' => '',
            'profile_form[gender]' => 'female',
        ]);

        self::assertResponseIsUnprocessable();

        self::assertSelectorExists('#profile_form_firstname_error1');
        self::assertSelectorTextContains(
            '#profile_form_firstname_error1',
            'Please enter your name.',
        );
        self::assertSelectorExists('#profile_form_lastname_error1');
        self::assertSelectorTextContains(
            '#profile_form_lastname_error1',
            'Please enter your name.',
        );
    }

    public function testUserCannotUseSingleCharacterForFirstnameAndLastname(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[firstname]' => 'A',
            'profile_form[lastname]' => 'S',
            'profile_form[gender]' => 'female',
        ]);

        self::assertResponseIsUnprocessable();

        self::assertSelectorExists('#profile_form_firstname_error1');
        self::assertSelectorTextContains(
            '#profile_form_firstname_error1',
            'At least 2 characters, please.',
        );
        self::assertSelectorExists('#profile_form_lastname_error1');
        self::assertSelectorTextContains(
            '#profile_form_lastname_error1',
            'At least 2 characters, please.',
        );
    }

    public function testUserCanDeleteAccountEvenAfterRequestingPasswordReset(): void
    {
        $user = $this->createUser([
            'email' => 'me@example.com',
        ]);

        // Request password reset first
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

        // Now go to profile and delete account
        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $this->client->submitForm('profile-delete-btn');
        self::assertResponseRedirects('/en/profile/delete/success');

        UserFactory::assert()->notExists([
            'id' => $user->getId(),
        ]);
    }

    public function testUserReceivesSuccessNotificationWhenProfileIsUpdated(): void
    {
        $user = $this->createUser([
            'language' => 'en',
        ]);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[firstname]' => 'John',
            'profile_form[lastname]' => 'Doe',
            'profile_form[gender]' => 'male',
        ]);

        self::assertResponseRedirects($this->path);

        $flashBag = $this->flashBag();

        self::assertTrue($flashBag->has(NotificationType::SUCCESS->value));
        $notifications = $flashBag->get(NotificationType::SUCCESS->value);

        self::assertCount(1, $notifications);
        $first = $notifications[0];
        Assert::string($first);
        $notification = json_decode(
            json: $first,
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($notification);

        self::assertSame('Got it!', $notification['title']);
        self::assertSame(
            'Your profile is up to date.',
            $notification['message'],
        );
    }

    public function testUserDoesNotReceiveNotificationWhenProfileUpdateFails(): void
    {
        $user = $this->createUser([
            'language' => 'en',
        ]);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[firstname]' => '',
            'profile_form[lastname]' => '',
            'profile_form[gender]' => 'male',
        ]);

        self::assertResponseIsUnprocessable();

        $flashBag = $this->flashBag();

        self::assertFalse($flashBag->has(NotificationType::SUCCESS->value));
    }

    public function testUserReceivesNotificationInNewLanguageWhenLanguageChanges(): void
    {
        $user = $this->createUser([
            'language' => 'en',
        ]);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        // Change language from English to German and submit profile
        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[firstname]' => 'John',
            'profile_form[lastname]' => 'Doe',
            'profile_form[gender]' => 'male',
            'profile_form[language]' => 'de',
        ]);

        self::assertResponseRedirects($this->path);

        $flashBag = $this->flashBag();

        self::assertTrue($flashBag->has(NotificationType::SUCCESS->value));
        $notifications = $flashBag->get(NotificationType::SUCCESS->value);

        self::assertCount(1, $notifications);
        $first = $notifications[0];
        Assert::string($first);
        $notification = json_decode(
            json: $first,
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($notification);

        // Assert that notification is in German (the new language)
        self::assertSame('Passt!', $notification['title']);
        self::assertSame(
            'Dein Profil ist auf dem neuesten Stand.',
            $notification['message'],
        );

        // Also verify the user's language was actually changed
        UserFactory::assert()->exists([
            'id' => $user->getId(),
            'language' => 'de',
        ]);
    }

    public function testUserCannotDeleteAccountWithInvalidOrMissingCsrfToken(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);

        // Attempt delete with invalid CSRF token
        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/app/profile/delete',
            parameters: ['_token' => 'completely_invalid_token_12345'],
        );

        self::assertResponseRedirects('/app/profile');
        UserFactory::assert()->exists([
            'id' => $user->getId(),
        ]);

        // Attempt delete without any CSRF token
        $this->client->request(
            method: Request::METHOD_POST,
            uri: '/app/profile/delete',
        );

        self::assertResponseRedirects('/app/profile');
        UserFactory::assert()->exists([
            'id' => $user->getId(),
        ]);
    }

    public function testUserCannotSubmitExtremelyLongNames(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $extremelyLongName = str_repeat(
            string: 'A',
            times: 256,
        ); // Max length is 255

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[firstname]' => $extremelyLongName,
            'profile_form[lastname]' => $extremelyLongName,
            'profile_form[gender]' => 'male',
        ]);

        self::assertResponseIsUnprocessable();

        self::assertSelectorExists('#profile_form_firstname_error1');
        self::assertSelectorTextContains(
            '#profile_form_firstname_error1',
            'This value is too long.',
        );
        self::assertSelectorExists('#profile_form_lastname_error1');
        self::assertSelectorTextContains(
            '#profile_form_lastname_error1',
            'This value is too long.',
        );
    }

    public function testUserCannotDeleteProfileViaGetMethod(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);

        // Attempt to access delete URL with GET method (should not be allowed)
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile/delete',
        );

        // Stricter than the original, which also accepted a redirect: the route is
        // POST-only, so the RouterListener answers before the firewall ever runs and
        // 405 is the only reachable outcome. Accepting a redirect would let the test
        // pass with methods removed, on the controller's own CSRF redirect.
        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);

        // User should still exist
        UserFactory::assert()->exists([
            'id' => $user->getId(),
        ]);
    }

    /**
     * Not in the original: a rejected file must not leave a row behind.
     */
    public function testUserCannotUploadSomethingThatIsNotAnImage(): void
    {
        $user = $this->createUser();

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $form = $this->client->getCrawler()
            ->selectButton('Save')
            ->form();

        [$values, $files] = $this->addUploadToForm(
            form: $form,
            filePath: 'composer.json',
            field: '[profile_form][picture][file]',
        );

        $this->client->request(
            method: $form->getMethod(),
            uri: $form->getUri(),
            parameters: $values,
            files: $files,
        );

        self::assertResponseIsUnprocessable();

        // Nothing was flushed: the rejected file never became a row.
        UploadFactory::assert()->empty();
    }

    public function testUserCanRequestEmailChange(): void
    {
        $user = $this->createUser([
            'email' => 'old@example.com',
            'language' => 'en',
        ]);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        self::assertResponseIsSuccessful();

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[email]' => 'new@example.com',
        ]);

        self::assertResponseRedirects($this->path);

        // Email should NOT have changed yet
        UserFactory::assert()->exists([
            'id' => $user->getId(),
            'email' => 'old@example.com',
        ]);

        // 2 emails: confirmation to new + notification to old
        self::assertQueuedEmailCount(2);

        $confirmationEmail = self::getSentEmail(0);
        self::assertSame(
            'new@example.com',
            $confirmationEmail->getTo()[0]
                ->getAddress(),
        );

        $notificationEmail = self::getSentEmail(1);
        self::assertSame(
            'old@example.com',
            $notificationEmail->getTo()[0]
                ->getAddress(),
        );

        // Confirmation email contains link
        $confirmationHtml = self::getMailerMessageHtml(0);
        self::assertStringContainsString('confirm-email', $confirmationHtml);

        // Flash notification
        $flashBag = $this->flashBag();
        self::assertTrue($flashBag->has(NotificationType::SUCCESS->value));
        $notifications = $flashBag->get(NotificationType::SUCCESS->value);
        $first = $notifications[0];
        Assert::string($first);
        $notification = json_decode(
            json: $first,
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($notification);
        self::assertSame('Confirmation email sent', $notification['title']);
    }

    public function testUserCanConfirmEmailChange(): void
    {
        $user = $this->createUser([
            'email' => 'old@example.com',
        ]);
        $token = bin2hex(random_bytes(32));

        EmailChangeRequestFactory::createOne([
            'user' => $user,
            'newEmail' => 'new@example.com',
            'token' => $token,
        ]);

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile/confirm-email/' . $token,
        );

        self::assertResponseRedirects($this->path);

        // User is auto-logged in after confirmation (route has no #[IsGranted])
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertRouteSame('app_profile');

        // Email should now be changed
        UserFactory::assert()->exists([
            'email' => 'new@example.com',
        ]);
    }

    /**
     * Not in the original: a forwarded link must not hand the session to whoever opens
     * it. The address still moves -- the token says whose -- but the reader stays who
     * they were.
     */
    public function testUserCannotBeSwappedByAForeignEmailChangeLink(): void
    {
        $owner = $this->createUser([
            'email' => 'owner@example.com',
        ]);
        $bystander = $this->createUser([
            'email' => 'bystander@example.com',
        ]);
        $token = bin2hex(random_bytes(32));

        EmailChangeRequestFactory::createOne([
            'user' => $owner,
            'newEmail' => 'moved@example.com',
            'token' => $token,
        ]);

        $this->client->loginUser($bystander);
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile/confirm-email/' . $token,
        );

        self::assertResponseRedirects($this->path);
        UserFactory::assert()->exists([
            'id' => $owner->getId(),
            'email' => 'moved@example.com',
        ]);

        $stillSignedIn = $this->getSignedInUser();
        self::assertInstanceOf(User::class, $stillSignedIn);
        self::assertSame($bystander->getId(), $stillSignedIn->getId());
    }

    public function testUserCannotChangeEmailToExistingEmail(): void
    {
        $user = $this->createUser([
            'email' => 'user@example.com',
            'language' => 'en',
        ]);
        UserFactory::createOne([
            'email' => 'taken@example.com',
        ]);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[email]' => 'taken@example.com',
        ]);

        self::assertResponseRedirects($this->path);

        // No emails sent
        self::assertQueuedEmailCount(0);

        // Error notification
        $flashBag = $this->flashBag();
        self::assertTrue($flashBag->has(NotificationType::ERROR->value));
    }

    public function testErrorNotificationRendersOnTheTargetPage(): void
    {
        $user = $this->createUser([
            'email' => 'user@example.com',
            'language' => 'en',
        ]);
        UserFactory::createOne([
            'email' => 'taken@example.com',
        ]);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);

        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[email]' => 'taken@example.com',
        ]);

        self::assertResponseRedirects($this->path);

        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#notifications [aria-live="assertive"]');
    }

    public function testInvalidEmailChangeTokenShowsError(): void
    {
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile/confirm-email/nonexistent-token',
        );

        self::assertResponseRedirects($this->path);
        $this->assertErrorNotificationContains('Link expired');
    }

    public function testExpiredEmailChangeTokenShowsError(): void
    {
        $user = $this->createUser([
            'email' => 'old@example.com',
        ]);
        $token = bin2hex(random_bytes(32));

        EmailChangeRequestFactory::createOne([
            'user' => $user,
            'newEmail' => 'new@example.com',
            'token' => $token,
            'expiresAt' => new DatePoint('-1 hour'),
        ]);

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile/confirm-email/' . $token,
        );

        self::assertResponseRedirects($this->path);

        // Email unchanged
        UserFactory::assert()->exists([
            'id' => $user->getId(),
            'email' => 'old@example.com',
        ]);

        $this->assertErrorNotificationContains('Link expired');
    }

    public function testNewEmailChangeRequestInvalidatesPreviousOne(): void
    {
        $user = $this->createUser([
            'email' => 'old@example.com',
            'language' => 'en',
        ]);

        $firstToken = bin2hex(random_bytes(32));
        EmailChangeRequestFactory::createOne([
            'user' => $user,
            'newEmail' => 'first@example.com',
            'token' => $firstToken,
        ]);

        // Request a second email change
        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[email]' => 'second@example.com',
        ]);
        self::assertResponseRedirects($this->path);

        // Capture the confirmation URL before the next request (mailer resets between them)
        $confirmationHtml = self::getMailerMessageHtml(0);
        $confirmUrl = self::extractEmailUrl(
            html: $confirmationHtml,
            path: 'confirm-email',
        );

        // First token should be invalidated
        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile/confirm-email/' . $firstToken,
        );
        self::assertResponseRedirects($this->path);
        UserFactory::assert()->exists([
            'id' => $user->getId(),
            'email' => 'old@example.com',
        ]);

        // Second token (from email) should work
        $this->client->request(method: Request::METHOD_GET, uri: $confirmUrl);
        self::assertResponseRedirects($this->path);

        UserFactory::assert()->exists([
            'email' => 'second@example.com',
        ]);
    }

    public function testEmailFieldShowsCurrentEmail(): void
    {
        $user = $this->createUser([
            'email' => 'myemail@example.com',
        ]);

        $this->client->loginUser($user);
        $crawler = $this->client->request(
            method: Request::METHOD_GET,
            uri: $this->path,
        );
        self::assertResponseIsSuccessful();

        $emailField = $crawler->filter('#profile_form_email');
        self::assertSame('myemail@example.com', $emailField->attr('value'));
    }

    public function testUserCannotConfirmEmailChangeWhenEmailWasTakenInMeantime(): void
    {
        $user = $this->createUser([
            'email' => 'old@example.com',
        ]);
        $token = bin2hex(random_bytes(32));

        EmailChangeRequestFactory::createOne([
            'user' => $user,
            'newEmail' => 'new@example.com',
            'token' => $token,
        ]);

        // Someone else registered with the desired email in the meantime
        UserFactory::createOne([
            'email' => 'new@example.com',
        ]);

        $this->client->request(
            method: Request::METHOD_GET,
            uri: '/app/profile/confirm-email/' . $token,
        );

        self::assertResponseRedirects($this->path);

        // Email must remain unchanged
        UserFactory::assert()->exists([
            'id' => $user->getId(),
            'email' => 'old@example.com',
        ]);

        $this->assertErrorNotificationContains(
            'This email address is already in use.',
        );
    }

    public function testUserCannotRequestEmailChangeTooOften(): void
    {
        $user = $this->createUser([
            'email' => 'old@example.com',
            'language' => 'en',
        ]);

        $this->client->loginUser($user);

        // Disable kernel reboot so the rate limiter cache is shared between
        // the consume() call below and the actual HTTP request handler.
        $this->client->disableReboot();

        $limiterFactory = self::getContainer()->get('limiter.email_change');
        Assert::isInstanceOf(
            value: $limiterFactory,
            class: RateLimiterFactoryInterface::class,
        );
        $limiter = $limiterFactory->create((string) $user->getId());
        $limiter->consume(3);

        $this->client->request(method: Request::METHOD_GET, uri: $this->path);
        $this->client->submitForm(button: 'Save', fieldValues: [
            'profile_form[email]' => 'new@example.com',
        ]);
        self::assertResponseRedirects($this->path);

        $flashBag = $this->flashBag();
        self::assertTrue($flashBag->has(NotificationType::ERROR->value));
        $notifications = $flashBag->get(NotificationType::ERROR->value);
        $first = $notifications[0];
        Assert::string($first);
        $notification = json_decode(
            json: $first,
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($notification);
        self::assertSame(
            'Too many requests. Please wait a few minutes before trying again.',
            $notification['message'],
        );

        // No emails for the throttled request
        self::assertQueuedEmailCount(0);

        // Reset limiter so other tests in the same worker aren't affected
        $limiter->reset();
    }

    /**
     * Psalm resolves Foundry's createOne() to the factory's template parameter and
     * lands on mixed, so without this every account would need narrowing where it is
     * used.
     *
     * @param array<string, mixed> $attributes
     */
    private function createUser(array $attributes = []): User
    {
        $user = UserFactory::createOne($attributes);
        Assert::isInstanceOf(value: $user, class: User::class);

        return $user;
    }

    private function createUserWithPicture(): User
    {
        return UserFactory::new()->withPicture()
            ->create();
    }

    private function reload(User $user): User
    {
        $reloaded = UserFactory::repository()->find([
            'id' => $user->getId(),
        ]);
        Assert::isInstanceOf(value: $reloaded, class: User::class);

        return $reloaded;
    }

    /**
     * The exact wording, because both failure paths of the email change flow
     * raise an ERROR flash -- has() alone cannot tell them apart.
     */
    private function assertErrorNotificationContains(string $needle): void
    {
        $flashes = $this->flashBag()
            ->get(NotificationType::ERROR->value);
        self::assertCount(1, $flashes);

        $flash = $flashes[0];
        Assert::string($flash);
        self::assertStringContainsString($needle, $flash);
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
