<?php

declare(strict_types=1);

namespace App\Tests\Trait;

use App\Service\FileHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait TestFileHelperTrait
{
    public function getRandomImage(): UploadedFile
    {
        return $this->getTestFileHelper()
            ->getRandomImage();
    }

    private function getTestImage(bool $asJpeg = false): UploadedFile
    {
        return $this->getTestFileHelper()
            ->getTestImage($asJpeg);
    }

    private function getTestFileHelper(): FileHelper
    {
        /** @var FileHelper $fileHelper */
        $fileHelper = self::getContainer()->get(FileHelper::class);

        return $fileHelper;
    }
}
