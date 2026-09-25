<?php

declare(strict_types=1);

namespace App\Service\Upload;

use App\Entity\Upload\UploadInterface;
use App\Event\UploadChangedEvent;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Webmozart\Assert\Assert;

final readonly class UploadService
{
    public function __construct(
        private UploadServiceHelper $serviceHelper,
        private EventDispatcherInterface $eventDispatcher,
        private FilesystemOperator $mediaStorage,
    ) {
    }

    public function upload(
        UploadInterface $upload,
        ?string $filename = null,
    ): void {
        if (! $upload->hasFile()) {
            return;
        }

        $oldUpload = clone $upload;

        $this->processUploadActions(upload: $upload, filename: $filename);
        $this->eventDispatcher->dispatch(
            new UploadChangedEvent(oldUpload: $oldUpload),
        );
    }

    /**
     * Takes the upload out of a form field and stores it.
     *
     * Templated rather than FormInterface<mixed>: the form types here declare their
     * data class, and FormInterface is not covariant, so a FormInterface<User> would
     * not satisfy a mixed parameter.
     *
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    public function uploadFromForm(
        FormInterface $form,
        string $fieldName = 'picture',
    ): void {
        $upload = $form->get($fieldName)
            ->getData();
        Assert::nullOrIsInstanceOf(
            value: $upload,
            class: UploadInterface::class,
        );

        if (! $upload instanceof UploadInterface) {
            return;
        }

        $this->upload($upload);
    }

    public function processUploadActions(
        UploadInterface $upload,
        ?string $filename = null,
    ): void {
        /** @var UploadedFile $file */
        $file = $upload->getFile();

        $filename = $this->serviceHelper->getFilename(
            upload: $upload,
            filename: $filename,
        );

        $upload->setFilename($filename);
        $upload->setOriginalFilename($file->getClientOriginalName());
        $upload->setMimeType($file->getClientMimeType());
        $upload->setSize($file->getSize());
        $upload->setDimensions(
            $this->serviceHelper->generateDimensionsString($file),
        );

        if (! $upload->getId() instanceof Ulid) {
            $upload->setId(new Ulid());
        }

        $this->moveFile(file: $file, filename: $filename);
        $upload->setFile(null);
    }

    private function moveFile(UploadedFile $file, string $filename): void
    {
        $realPath = $file->getRealPath();
        Assert::string($realPath);

        $stream = fopen(filename: $realPath, mode: 'rb');
        Assert::resource($stream);

        // No visibility here: the storage says what it is. Forcing private would write
        // 0600 onto the local disk, where the files are served straight from public/.
        $this->mediaStorage->writeStream(
            location: '/' . $filename,
            contents: $stream,
        );

        fclose($stream);

        unlink($realPath);
    }
}
