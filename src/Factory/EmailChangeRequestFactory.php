<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\EmailChangeRequest;
use Override;
use Symfony\Component\Clock\DatePoint;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<EmailChangeRequest>
 */
final class EmailChangeRequestFactory extends PersistentObjectFactory
{
    #[Override]
    public static function class(): string
    {
        return EmailChangeRequest::class;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'newEmail' => self::faker()->unique()->safeEmail(),
            'expiresAt' => new DatePoint('+24 hours'),
            'token' => bin2hex(random_bytes(32)),
        ];
    }
}
