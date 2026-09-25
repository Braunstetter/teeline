<?php

declare(strict_types=1);

namespace App\Tests\Unit\Event\Listener;

use App\Event\Listener\TurnstileLoginListener;
use Override;
use PHPUnit\Framework\TestCase;
use PixelOpen\CloudflareTurnstileBundle\Http\CloudflareTurnstileHttpClient;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

final class TurnstileLoginListenerTest extends TestCase
{
    private RequestStack $requestStack;

    private MockHttpClient $httpClient;

    #[Override]
    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->httpClient = new MockHttpClient();
    }

    public function testRejectsALoginWithoutAToken(): void
    {
        try {
            $this->runListenerOnFormLogin(token: null);
            self::fail('A login without a token must not pass.');
        } catch (CustomUserMessageAuthenticationException) {
        }

        self::assertSame(0, $this->httpClient->getRequestsCount());
    }

    public function testRejectsALoginWithABogusToken(): void
    {
        $this->httpClient = new MockHttpClient($this->createSiteverifyResponse(
            success: false,
        ));

        $this->expectException(CustomUserMessageAuthenticationException::class);

        $this->runListenerOnFormLogin(token: 'made-up-by-a-bot');
    }

    public function testPassesALoginWithAValidToken(): void
    {
        $this->httpClient = new MockHttpClient($this->createSiteverifyResponse(
            success: true,
        ));

        $this->runListenerOnFormLogin(token: 'solved-challenge');

        self::assertSame(1, $this->httpClient->getRequestsCount());
    }

    public function testIgnoresTheChallengeWhenDisabled(): void
    {
        $this->runListenerOnFormLogin(token: null, enable: false);

        self::assertSame(0, $this->httpClient->getRequestsCount());
    }

    public function testIgnoresProgrammaticLogins(): void
    {
        $this->createListener()(new CheckPassportEvent(
            authenticator: self::createStub(AuthenticatorInterface::class),
            passport: $this->createPassport(),
        ));

        self::assertSame(0, $this->httpClient->getRequestsCount());
    }

    public function testRunsBeforeThePasswordCheck(): void
    {
        $attributes = new ReflectionClass(TurnstileLoginListener::class)
            ->getAttributes(AsEventListener::class);
        self::assertCount(1, $attributes);

        $listener = $attributes[0]->newInstance();
        self::assertSame(CheckPassportEvent::class, $listener->event);
        self::assertGreaterThan(0, $listener->priority);
    }

    private function runListenerOnFormLogin(
        ?string $token,
        bool $enable = true,
    ): void {
        $this->requestStack->push(Request::create(
            uri: '/app/en/login',
            method: Request::METHOD_POST,
            parameters: $token === null ? [] : ['cf-turnstile-response' => $token],
        ));

        $this->createListener($enable)(new CheckPassportEvent(
            authenticator: self::createStub(FormLoginAuthenticator::class),
            passport: $this->createPassport(),
        ));
    }

    private function createListener(bool $enable = true): TurnstileLoginListener
    {
        return new TurnstileLoginListener(
            enable: $enable,
            requestStack: $this->requestStack,
            turnstileHttpClient: new CloudflareTurnstileHttpClient(
                secret: 'test-secret',
                httpClient: $this->httpClient,
                logger: new NullLogger(),
            ),
        );
    }

    private function createSiteverifyResponse(bool $success): MockResponse
    {
        return new MockResponse(json_encode(
            value: ['success' => $success],
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    private function createPassport(): SelfValidatingPassport
    {
        return new SelfValidatingPassport(
            userBadge: new UserBadge('probe@example.com'),
        );
    }
}
