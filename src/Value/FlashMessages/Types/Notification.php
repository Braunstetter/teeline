<?php

declare(strict_types=1);

namespace App\Value\FlashMessages\Types;

use App\Enum\NotificationType;
use JsonException;

final readonly class Notification
{
    public function __construct(
        public NotificationType $type,
        public string $title,
        public ?string $message = null,
        public bool $canBeDismissed = true,
        public ?int $timeToDestroy = 3,
        public bool $confetti = false,
    ) {
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode(
            value: get_object_vars($this),
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
