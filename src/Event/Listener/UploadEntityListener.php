<?php

declare(strict_types=1);

namespace App\Event\Listener;

use App\Entity\Upload\Upload;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class UploadEntityListener
{
    /**
     * Read before the DELETE, deleted after the commit: on a cascade the upload is an
     * uninitialised proxy, and reading it afterwards throws EntityNotFoundException.
     *
     * @var list<string>
     */
    private array $removedPaths = [];

    public function __construct(
        #[Autowire(param: 'upload_public_dir')]
        private readonly string $uploadPublicDir,
        private readonly LoggerInterface $logger,
        private readonly CacheManager $cacheManager,
        private readonly FilesystemOperator $mediaStorage,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $upload = $args->getObject();

        if (! $upload instanceof Upload) {
            return;
        }

        $path = $upload->getFullPath();

        if ($path !== null) {
            $this->removedPaths[] = $path;
        }
    }

    public function postFlush(): void
    {
        $paths = $this->removedPaths;
        $this->removedPaths = [];

        foreach ($paths as $path) {
            $this->removeFile($path);
        }
    }

    private function removeFile(string $path): void
    {
        if ($this->mediaStorage->fileExists($path)) {
            $this->mediaStorage->delete($path);
        } else {
            $this->logger->debug(
                message: 'While removing upload, the file was not found.',
                context: [
                    'fullPath' => $this->uploadPublicDir . '/' . $path,
                ],
            );
        }

        $this->cacheManager->remove($path);
    }
}
