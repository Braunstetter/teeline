<?php

declare(strict_types=1);

namespace App\Service\Upload;

use App\Entity\Upload\UploadInterface;
use App\Repository\UploadRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Webmozart\Assert\Assert;

final readonly class UploadNamingService
{
    public function __construct(
        private UploadRepository $repository,
    ) {
    }

    public function generateFilename(UploadInterface $upload): string
    {
        $file = $upload->getFile();
        Assert::isInstanceOf(value: $file, class: UploadedFile::class);

        $name = pathinfo(
            path: $file->getClientOriginalName(),
            flags: PATHINFO_FILENAME,
        );
        $extension = $file->getClientOriginalExtension();

        $taken = $this->repository->findFilenamesStartingWith($name);

        $num = 0;
        while (in_array(
            needle: $this->numberedName(
                name: $name,
                extension: $extension,
                num: $num,
            ),
            haystack: $taken,
            strict: true,
        )) {
            ++$num;
        }

        return $this->numberedName(
            name: $name,
            extension: $extension,
            num: $num,
        );
    }

    private function numberedName(
        string $name,
        string $extension,
        int $num,
    ): string {
        if ($num !== 0) {
            $name = sprintf('%s-%d', $name, $num);
        }

        return $name . '.' . $extension;
    }
}
