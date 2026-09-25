<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Upload\UploadInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class UploadChangedEvent extends Event
{
    public function __construct(
        private readonly UploadInterface $oldUpload,
    ) {
    }

    public function oldUpload(): UploadInterface
    {
        return $this->oldUpload;
    }
}
