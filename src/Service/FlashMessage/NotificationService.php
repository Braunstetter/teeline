<?php

declare(strict_types=1);

namespace App\Service\FlashMessage;

use App\Enum\NotificationType;
use App\Value\FlashMessages\Types\Notification;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final readonly class NotificationService
{
    public function __construct(
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    public function addSuccessNotification(
        ?string $title = null,
        ?string $message = null,
        ?int $timeToDestroy = 3,
    ): void {
        $this->addFlash(new Notification(
            type: NotificationType::SUCCESS,
            title: $title ?? $this->translator->trans(
                'notification.success.title',
            ),
            message: $message,
            timeToDestroy: $timeToDestroy,
        ));
    }

    public function addErrorNotification(
        ?string $title = null,
        ?string $message = null,
        ?int $timeToDestroy = 3,
    ): void {
        $this->addFlash(new Notification(
            type: NotificationType::ERROR,
            title: $title ?? $this->translator->trans(
                'notification.error.title',
            ),
            message: $message,
            timeToDestroy: $timeToDestroy,
        ));
    }

    public function addWelcomeNotification(): void
    {
        $this->addFlash(new Notification(
            type: NotificationType::SUCCESS,
            title: $this->translator->trans(
                id: 'registration.welcome.title',
                parameters: [],
                domain: 'security',
            ),
            message: $this->translator->trans(
                id: 'registration.welcome.message',
                parameters: [],
                domain: 'security',
            ),
            timeToDestroy: 15,
            confetti: true,
        ));
    }

    private function addFlash(Notification $notification): void
    {
        $session = $this->requestStack->getSession();
        Assert::isInstanceOf(
            value: $session,
            class: FlashBagAwareSessionInterface::class,
        );

        $session->getFlashBag()
            ->add(
                type: $notification->getType()
                    ->value,
                message: $notification->toJson(),
            );
    }
}
