<?php

declare(strict_types=1);

namespace App\Service\Upload;

use App\Entity\Upload\UploadInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Webmozart\Assert\Assert;

final readonly class UploadServiceHelper
{
    public function __construct(
        private UploadNamingService $namingService,
    ) {
    }

    public function getFilename(
        UploadInterface $upload,
        ?string $filename,
    ): string {
        /** @var UploadedFile $file */
        $file = $upload->getFile();
        $fileExtension = $file->guessExtension();
        Assert::notNull($fileExtension);

        if ($filename === null) {
            $filename = $this->namingService->generateFilename(
                $upload,
            );
        } else {
            $filename .= '.' . $fileExtension;
        }

        return $filename;
    }

    public function guessMimeType(string $realFullPath): null|string
    {
        return new MimeTypes()
            ->guessMimeType($realFullPath);
    }

    public function generateDimensionsString(UploadedFile $file): string|null
    {
        if (! $this->fileShouldHaveDimensions($file)) {
            return null;
        }

        $dimensionsData = $this->getDimensions($file);
        $dimensions = $this->extractDimensionArray($dimensionsData);

        if ($dimensions === null) {
            return null;
        }

        return $dimensions['width'] . 'x' . $dimensions['height'];
    }

    public function fileShouldHaveDimensions(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();

        if ($mimeType === null) {
            return false;
        }

        return \str_starts_with(
            haystack: $mimeType,
            needle: 'image/',
        ) && $mimeType !== 'image/svg+xml';
    }

    /**
     * @return array<array-key, mixed>|false
     */
    private function getDimensions(UploadedFile $file): array|false
    {
        return @getimagesize($file->getRealPath());
    }

    /**
     * @param array<array-key, mixed>|false $dimensions
     * @return array{width: int, height: int}|null
     */
    private function extractDimensionArray(array|false $dimensions): array|null
    {
        if ($dimensions === false) {
            return null;
        }

        $indexedArray = array_slice(array: $dimensions, offset: 0, length: 2);
        Assert::keyExists(array: $indexedArray, key: 0);
        Assert::keyExists(array: $indexedArray, key: 1);
        Assert::integer($indexedArray[0]);
        Assert::integer($indexedArray[1]);

        return [
            'width' => $indexedArray[0],
            'height' => $indexedArray[1],
        ];
    }
}
