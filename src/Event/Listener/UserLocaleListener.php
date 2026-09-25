<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class UserLocaleListener
{
    public function __construct(
        private Security $security,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER, priority: 15)]
    public function onKernelRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $user = $this->security->getUser();

        if (! $user instanceof User) {
            return;
        }

        $userPreferredLocale = $user->getLanguage();
        if ($userPreferredLocale === null) {
            return;
        }

        $this->localeSwitcher->setLocale($userPreferredLocale);
        $request->setLocale($userPreferredLocale);
    }
}
