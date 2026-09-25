<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;

final class PasswordRequirements extends Compound
{
    /**
     * @param array<string, mixed> $options
     *
     * @return array<int, Constraint>
     */
    #[Override]
    protected function getConstraints(array $options): array
    {
        return [
            new NotBlank(message: 'password_blank'),
            new Length(
                min: 8,
                max: 4096,
                minMessage: 'password_too_short',
            ),
            new NotCompromisedPassword(message: 'password_compromised'),
        ];
    }
}
