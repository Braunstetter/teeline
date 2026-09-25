<?php

declare(strict_types=1);

namespace App\Event\Listener;

use PixelOpen\CloudflareTurnstileBundle\Http\CloudflareTurnstileHttpClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Webmozart\Assert\Assert;

/**
 * form_login never validates the login form, so this checks the captcha
 * instead — before the password check, which hashes at priority 0.
 */
#[AsEventListener(event: CheckPassportEvent::class, priority: 512)]
final readonly class TurnstileLoginListener
{
    public function __construct(
        #[Autowire(param: 'pixelopen_cloudflare_turnstile.enable')]
        private bool $enable,
        private RequestStack $requestStack,
        #[Autowire(service: 'turnstile.http_client')]
        private CloudflareTurnstileHttpClient $turnstileHttpClient,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        if (! $this->enable) {
            return;
        }

        if (! $event->getAuthenticator() instanceof FormLoginAuthenticator) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        Assert::notNull($request);

        $token = $request->request->getString('cf-turnstile-response');

        if ($token === '' || ! $this->turnstileHttpClient->verifyResponse(
            $token,
        )) {
            throw new CustomUserMessageAuthenticationException(
                'login.form.captcha_failed',
            );
        }
    }
}
