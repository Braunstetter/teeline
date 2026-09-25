<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Braunstetter\Helper\Arr;
use JsonException;
use Twig\Attribute\AsTwigFilter;

/**
 * The two filters the attribute-carrying partials use: they let a caller hand attributes
 * into an embed without the embed overwriting what it gets.
 */
final readonly class AppExtension
{
    /**
     * @param array<array-key, mixed> $array
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    #[AsTwigFilter('attach')]
    public function attach(array $array, array $data): array
    {
        return Arr::attach(array: $array, data: $data);
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    #[AsTwigFilter('attachClass')]
    public function attachClass(array $array, string $class): array
    {
        return Arr::attachClass(array: $array, class: $class);
    }

    #[AsTwigFilter('dedent', isSafe: ['html'])]
    public function dedent(string $text): string
    {
        $lines = explode(separator: "\n", string: $text);

        $indents = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $indents[] = strlen($line) - strlen(ltrim($line));
        }

        if ($indents === []) {
            return $text;
        }

        $common = min($indents);

        return implode(
            separator: "\n",
            array: array_map(
                callback: static fn (string $line): string => substr(
                    string: $line,
                    offset: $common,
                ),
                array: $lines,
            ),
        );
    }

    /**
     * Notifications travel through the flash bag as JSON, because a flash bag holds
     * strings. Returns the input untouched when it is not JSON.
     */
    #[AsTwigFilter('json_decode')]
    public function jsonDecode(string $value): mixed
    {
        try {
            return json_decode(
                json: $value,
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $value;
        }
    }
}
