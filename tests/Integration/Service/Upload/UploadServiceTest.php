<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Upload;

use App\Entity\Upload\Upload;
use App\Service\FileHelper;
use App\Service\Upload\UploadService;
use App\Tests\Trait\TestFileHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\File;
use Webmozart\Assert\Assert;

final class UploadServiceTest extends KernelTestCase
{
    use TestFileHelperTrait;

    private FilesystemOperator $filesystem;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $filesystem = self::getContainer()->get('media.storage');
        Assert::isInstanceOf(
            value: $filesystem,
            class: FilesystemOperator::class,
        );
        $this->filesystem = $filesystem;
    }

    public function testServiceCanUploadFileAndPersistToDatabase(): void
    {
        $upload = new Upload($this->getTestImage());
        $entityManager = $this->entityManager();

        $this->uploader()
            ->upload($upload);

        $entityManager->persist($upload);
        $entityManager->flush();

        self::assertTrue(
            $this->filesystem->fileExists($this->fullPath($upload)),
        );
        self::assertSame('avatar.png', $upload->getFullPath());
        self::assertSame('avatar.png', $upload->getFilename());
        self::assertSame('avatar.png', $upload->getOriginalFilename());
        self::assertSame('image/png', $upload->getMimeType());
        self::assertSame('200x200', $upload->dimensions());
        self::assertSame(10808, $upload->size());
    }

    public function testServiceCanUpdateExistingUploadFile(): void
    {
        $uploader = $this->uploader();
        $entityManager = $this->entityManager();

        $upload = new Upload($this->getTestImage());
        $uploader->upload($upload);
        $entityManager->persist($upload);
        $entityManager->flush();

        $oldFilePath = $this->fullPath($upload);
        $upload->setFile(
            $this->fileHelper()
                ->upload('assets/images/fixtures/puppy-2.jpg'),
        );

        self::assertTrue($this->filesystem->fileExists($oldFilePath));
        self::assertNotSame('puppy-2.jpg', $upload->getFilename());

        $uploader->upload($upload);

        self::assertSame('puppy-2.jpg', $upload->getFilename());
        self::assertSame('puppy-2.jpg', $upload->getOriginalFilename());
        self::assertSame('puppy-2.jpg', $upload->getFullPath());
        self::assertSame('image/jpeg', $upload->getMimeType());
        self::assertSame('100x148', $upload->dimensions());
        self::assertNotInstanceOf(File::class, $upload->getFile());
        self::assertTrue(
            $this->filesystem->fileExists($this->fullPath($upload)),
        );
        self::assertFalse($this->filesystem->fileExists($oldFilePath));
    }

    public function testServiceCanDeleteUploadFileFromFilesystem(): void
    {
        $entityManager = $this->entityManager();

        $upload = new Upload($this->getTestImage());
        $this->uploader()
            ->upload($upload);
        $entityManager->persist($upload);
        $entityManager->flush();

        self::assertTrue(
            $this->filesystem->fileExists($this->fullPath($upload)),
        );

        $entityManager->remove($upload);
        $entityManager->flush();

        self::assertFalse(
            $this->filesystem->fileExists($this->fullPath($upload)),
        );
    }

    private function uploader(): UploadService
    {
        /** @var UploadService $uploader */
        $uploader = self::getContainer()->get(UploadService::class);

        return $uploader;
    }

    private function fileHelper(): FileHelper
    {
        /** @var FileHelper $fileHelper */
        $fileHelper = self::getContainer()->get(FileHelper::class);

        return $fileHelper;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(
            'doctrine.orm.entity_manager',
        );
        Assert::isInstanceOf(
            value: $entityManager,
            class: EntityManagerInterface::class,
        );

        return $entityManager;
    }

    private function fullPath(Upload $upload): string
    {
        $path = $upload->getFullPath();
        Assert::string($path);

        return $path;
    }
}
