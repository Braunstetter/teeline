<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case SUCCESS = 'notification_success';
    case ERROR = 'notification_error';

    public function icon(): string
    {
        return match ($this) {
            self::SUCCESS => 'heroicons:check',
            self::ERROR => 'heroicons:exclamation-triangle',
        };
    }

    /**
     * @return array<array-key, string>
     */
    public static function toArray(): array
    {
        return array_map(
            callback: static fn (self $type): string => $type->value,
            array: self::cases(),
        );
    }
}
