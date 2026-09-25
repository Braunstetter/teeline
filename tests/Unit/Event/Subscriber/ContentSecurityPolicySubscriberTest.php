<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event\Subscriber;

use App\Event\Subscriber\ContentSecurityPolicySubscriber;
use App\Service\Security\CspNonceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(ContentSecurityPolicySubscriber::class)]
final class ContentSecurityPolicySubscriberTest extends TestCase
{
    public function testsubscribesToKernelResponse(): void
    {
        $events = ContentSecurityPolicySubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testsetsHeaderFromStringDirectives(): void
    {
        $subscriber = $this->createSubscriber([
            'default-src' => "'self'",
            'script-src' => "'self' https://example.com",
        ]);

        $event = $this->createMainRequestEvent();
        $subscriber->onKernelResponse($event);

        self::assertSame(
            "default-src 'self'; script-src 'self' https://example.com",
            $event->getResponse()
                ->headers->get('Content-Security-Policy'),
        );
    }

    public function testhandlesBooleanDirectives(): void
    {
        $subscriber = $this->createSubscriber([
            'default-src' => "'self'",
            'upgrade-insecure-requests' => true,
        ]);

        $event = $this->createMainRequestEvent();
        $subscriber->onKernelResponse($event);

        self::assertSame(
            "default-src 'self'; upgrade-insecure-requests",
            $event->getResponse()
                ->headers->get('Content-Security-Policy'),
        );
    }

    public function testskipsFalseDirectives(): void
    {
        $subscriber = $this->createSubscriber([
            'default-src' => "'self'",
            'upgrade-insecure-requests' => false,
        ]);

        $event = $this->createMainRequestEvent();
        $subscriber->onKernelResponse($event);

        self::assertSame(
            "default-src 'self'",
            $event->getResponse()
                ->headers->get('Content-Security-Policy'),
        );
    }

    public function testdoesNotSetHeaderWhenDirectivesAreEmpty(): void
    {
        $subscriber = $this->createSubscriber([]);

        $event = $this->createMainRequestEvent();
        $subscriber->onKernelResponse($event);

        self::assertFalse(
            $event->getResponse()
                ->headers->has('Content-Security-Policy'),
        );
    }

    public function testdoesNotSetHeaderForSubRequests(): void
    {
        $subscriber = $this->createSubscriber([
            'default-src' => "'self'",
        ]);

        $event = $this->createSubRequestEvent();
        $subscriber->onKernelResponse($event);

        self::assertFalse(
            $event->getResponse()
                ->headers->has('Content-Security-Policy'),
        );
    }

    public function testincludesNonceInScriptSrc(): void
    {
        $subscriber = $this->createSubscriber([
            'default-src' => "'self'",
            'script-src' => "'self'",
        ], 'abc123def456');

        $event = $this->createMainRequestEvent();
        $subscriber->onKernelResponse($event);

        self::assertSame(
            "default-src 'self'; script-src 'self' 'nonce-abc123def456'",
            $event->getResponse()
                ->headers->get('Content-Security-Policy'),
        );
    }

    public function testdoesNotAddNonceToOtherDirectives(): void
    {
        $subscriber = $this->createSubscriber([
            'default-src' => "'self'",
            'style-src' => "'self' 'unsafe-inline'",
        ], 'abc123def456');

        $event = $this->createMainRequestEvent();
        $subscriber->onKernelResponse($event);

        self::assertSame(
            "default-src 'self'; style-src 'self' 'unsafe-inline'",
            $event->getResponse()
                ->headers->get('Content-Security-Policy'),
        );
    }

    /**
     * @param array<string, string|bool> $directives
     */
    private function createSubscriber(
        array $directives,
        ?string $nonce = null,
    ): ContentSecurityPolicySubscriber {
        $requestStack = new RequestStack();

        if ($nonce !== null) {
            $request = new Request();
            $request->attributes->set(key: '_csp_nonce', value: $nonce);
            $requestStack->push($request);
        }

        return new ContentSecurityPolicySubscriber(
            directives: $directives,
            nonceProvider: new CspNonceProvider(
                $requestStack,
            ),
        );
    }

    private function createMainRequestEvent(): ResponseEvent
    {
        return new ResponseEvent(
            kernel: self::createStub(HttpKernelInterface::class),
            request: new Request(),
            requestType: HttpKernelInterface::MAIN_REQUEST,
            response: new Response(),
        );
    }

    private function createSubRequestEvent(): ResponseEvent
    {
        return new ResponseEvent(
            kernel: self::createStub(HttpKernelInterface::class),
            request: new Request(),
            requestType: HttpKernelInterface::SUB_REQUEST,
            response: new Response(),
        );
    }
}
