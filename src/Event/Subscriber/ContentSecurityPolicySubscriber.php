<?php

declare(strict_types=1);

namespace App\Event\Subscriber;

use App\Service\Security\CspNonceProvider;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ContentSecurityPolicySubscriber implements EventSubscriberInterface
{
    /**
     * @param array<string, string|bool> $directives
     */
    public function __construct(
        #[Autowire(param: 'csp_directives')]
        private array $directives,
        private CspNonceProvider $nonceProvider,
    ) {
    }

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->directives === [] || ! $event->isMainRequest()) {
            return;
        }

        $headerValue = $this->buildHeaderValue();

        $event->getResponse()
            ->headers->set(
                key: 'Content-Security-Policy',
                values: $headerValue,
            );
    }

    private function buildHeaderValue(): string
    {
        $nonce = $this->nonceProvider->getNonce();
        $parts = [];

        foreach ($this->directives as $name => $value) {
            if ($value === true) {
                $parts[] = $name;
            } elseif (\is_string($value)) {
                if ($name === 'script-src' && $nonce !== '') {
                    $value .= \sprintf(" 'nonce-%s'", $nonce);
                }

                $parts[] = \sprintf('%s %s', $name, $value);
            }
        }

        return implode(separator: '; ', array: $parts);
    }
}
