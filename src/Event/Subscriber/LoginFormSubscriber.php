<?php

declare(strict_types=1);

namespace App\Event\Subscriber;

use Override;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LoginFormSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuthenticationUtils $authenticationUtils,
        private TranslatorInterface $translator,
    ) {
    }

    public function prefillLastUsername(FormEvent $event): void
    {
        $lastUsername = $this->authenticationUtils->getLastUsername();

        if ($lastUsername === '') {
            return;
        }

        $event->getForm()
            ->get('_username')
            ->setData($lastUsername);
    }

    public function addAuthenticationError(FormEvent $event): void
    {
        $error = $this->authenticationUtils->getLastAuthenticationError();

        if (! $error instanceof AuthenticationException) {
            return;
        }

        // Throttling and captcha failures carry a safe message of their own;
        // everything else collapses into one sentence, so nobody can tell an
        // unknown address from a wrong password.
        $message = $error instanceof TooManyLoginAttemptsAuthenticationException
            || $error instanceof CustomUserMessageAuthenticationException
            ? $this->translator->trans(
                id: $error->getMessageKey(),
                parameters: $error->getMessageData(),
                domain: 'security',
            )
            : $this->translator->trans(
                id: 'login.form.bad_credentials',
                domain: 'security',
            );

        $event->getForm()
            ->addError(new FormError($message));
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SET_DATA => [
                ['prefillLastUsername'],
                ['addAuthenticationError'],
            ],
        ];
    }
}
