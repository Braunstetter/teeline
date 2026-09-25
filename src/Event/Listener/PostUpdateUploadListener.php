<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Event\UploadChangedEvent;
use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PostUpdateUploadListener
{
    public function __construct(
        private CacheManager $cacheManager,
        private FilesystemOperator $mediaStorage,
    ) {
    }

    public function __invoke(UploadChangedEvent $event): void
    {
        $this->removeOldFile($event);
        $this->removeOldCachedFiles($event);
    }

    private function removeOldFile(UploadChangedEvent $event): void
    {
        $path = $event->oldUpload()
            ->getFullPath();
        if ($path === null) {
            return;
        }

        $this->mediaStorage->delete($path);
    }

    private function removeOldCachedFiles(UploadChangedEvent $event): void
    {
        $path = $event->oldUpload()
            ->getFullPath();

        if ($path === null) {
            return;
        }

        $this->cacheManager->remove($path);
    }
}
