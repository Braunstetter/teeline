<?php

declare(strict_types=1);

namespace App\Enum;

use Override;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Gender: string implements TranslatableInterface
{
    case Male = 'male';
    case Female = 'female';
    case Diverse = 'diverse';

    #[Override]
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null,
    ): string {
        return match ($this) {
            self::Male => $translator->trans(
                id: 'gender.male',
                parameters: [],
                domain: 'app',
                locale: $locale,
            ),
            self::Female => $translator->trans(
                id: 'gender.female',
                parameters: [],
                domain: 'app',
                locale: $locale,
            ),
            self::Diverse => $translator->trans(
                id: 'gender.diverse',
                parameters: [],
                domain: 'app',
                locale: $locale,
            ),
        };
    }
}
