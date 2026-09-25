<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Service\Security\CspNonceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CspNonceProvider::class)]
final class CspNonceProviderTest extends TestCase
{
    public function testgeneratesHexNonce(): void
    {
        $provider = $this->createProvider(new Request());

        $nonce = $provider->getNonce();

        self::assertSame(32, \strlen($nonce));
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $nonce);
    }

    public function testreturnsSameNonceForSameRequest(): void
    {
        $provider = $this->createProvider(new Request());

        $first = $provider->getNonce();
        $second = $provider->getNonce();

        self::assertSame($first, $second);
    }

    public function testreturnsEmptyStringWithoutRequest(): void
    {
        $provider = $this->createProvider(null);

        self::assertSame('', $provider->getNonce());
    }

    private function createProvider(?Request $request): CspNonceProvider
    {
        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        return new CspNonceProvider($requestStack);
    }
}
