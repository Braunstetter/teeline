<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CspNonceProvider
{
    private const string REQUEST_ATTRIBUTE = '_csp_nonce';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function getNonce(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (! $request instanceof Request) {
            return '';
        }

        $nonce = $request->attributes->getString(self::REQUEST_ATTRIBUTE);
        if ($nonce !== '') {
            return $nonce;
        }

        $nonce = bin2hex(random_bytes(16));
        $request->attributes->set(key: self::REQUEST_ATTRIBUTE, value: $nonce);

        return $nonce;
    }
}
