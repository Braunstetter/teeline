<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event\Subscriber;

use App\Event\Subscriber\LoginFormSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(LoginFormSubscriber::class)]
final class LoginFormSubscriberTest extends TestCase
{
    public function testSubscriberListensToPostSetData(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->createSubscriber());

        $listeners = $dispatcher->getListeners(FormEvents::POST_SET_DATA);

        self::assertCount(2, $listeners);
    }

    public function testUserGetsTheLastAddressBackAfterAFailedAttempt(): void
    {
        $subscriber = $this->createSubscriber([
            SecurityRequestAttributes::LAST_USERNAME => 'wer@example.com',
        ]);

        $form = $this->createLoginForm();
        $subscriber->prefillLastUsername(
            new FormEvent(form: $form, data: null),
        );

        self::assertSame('wer@example.com', $form->get('_username')
            ->getData());
    }

    public function testFieldStaysEmptyWithoutAPreviousAttempt(): void
    {
        $subscriber = $this->createSubscriber();

        $form = $this->createLoginForm();
        $subscriber->prefillLastUsername(
            new FormEvent(form: $form, data: null),
        );

        self::assertNull($form->get('_username')
            ->getData());
    }

    public function testUserCannotTellAnUnknownAddressFromAWrongPassword(): void
    {
        $subscriber = $this->createSubscriber([
            SecurityRequestAttributes::AUTHENTICATION_ERROR => new BadCredentialsException(),
        ]);

        $form = $this->createLoginForm();
        $subscriber->addAuthenticationError(
            new FormEvent(form: $form, data: null),
        );

        $errors = iterator_to_array($form->getErrors());

        self::assertCount(1, $errors);
        // The generic sentence, never the exception's own message -- that one
        // would say whether the account exists.
        self::assertSame(
            'login.form.bad_credentials',
            $errors[0]->getMessage(),
        );
    }

    public function testUserIsToldWhenTheyAreThrottled(): void
    {
        $subscriber = $this->createSubscriber([
            SecurityRequestAttributes::AUTHENTICATION_ERROR => new TooManyLoginAttemptsAuthenticationException(
                threshold: 5,
            ),
        ]);

        $form = $this->createLoginForm();
        $subscriber->addAuthenticationError(
            new FormEvent(form: $form, data: null),
        );

        $errors = iterator_to_array($form->getErrors());

        self::assertCount(1, $errors);
        self::assertSame(
            'Too many failed login attempts, please try again in %minutes% minutes.',
            $errors[0]->getMessage(),
        );
    }

    public function testUserIsToldWhenTheCaptchaFailed(): void
    {
        $subscriber = $this->createSubscriber([
            SecurityRequestAttributes::AUTHENTICATION_ERROR => new CustomUserMessageAuthenticationException(
                'login.form.captcha_failed',
            ),
        ]);

        $form = $this->createLoginForm();
        $subscriber->addAuthenticationError(
            new FormEvent(form: $form, data: null),
        );

        $errors = iterator_to_array($form->getErrors());

        self::assertCount(1, $errors);
        // A failed captcha says nothing about the account, so the honest
        // message is safe -- unlike the credential errors above.
        self::assertSame(
            'login.form.captcha_failed',
            $errors[0]->getMessage(),
        );
    }

    public function testFormStaysCleanWithoutAnAuthenticationError(): void
    {
        $subscriber = $this->createSubscriber();

        $form = $this->createLoginForm();
        $subscriber->addAuthenticationError(
            new FormEvent(form: $form, data: null),
        );

        self::assertCount(0, $form->getErrors());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createSubscriber(
        array $attributes = [],
    ): LoginFormSubscriber {
        $request = new Request(attributes: $attributes);

        $requestStack = new RequestStack([$request]);

        $translator = self::createStub(TranslatorInterface::class);
        // Returning the key keeps the test about the subscriber's choice of
        // message, not about the catalogue's wording.
        $translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id);

        return new LoginFormSubscriber(
            authenticationUtils: new AuthenticationUtils($requestStack),
            translator: $translator,
        );
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createLoginForm(): FormInterface
    {
        return Forms::createFormFactory()
            ->createBuilder()
            ->add(child: '_username', type: TextType::class)
            ->getForm();
    }
}
