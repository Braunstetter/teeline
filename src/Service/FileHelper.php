<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\Gender;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Webmozart\Assert\Assert;
use function Symfony\Component\String\u;

/**
 * Turns a file in the repository into an UploadedFile, for fixtures and tests. An
 * upload moves its source file, so everything here works on a copy in the temp dir.
 */
final readonly class FileHelper
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public static function createUploadedFileFromTmpFile(
        string $tmpFile,
    ): UploadedFile {
        $mimeType = new MimeTypes()
            ->guessMimeType($tmpFile);

        return new UploadedFile(
            path: $tmpFile,
            originalName: u($tmpFile)
                ->afterLast('/')
                ->toString(),
            mimeType: $mimeType,
            test: true,
        );
    }

    /**
     * Copies a file from the project into the temp dir and hands back that path. The
     * directory is keyed by process id, so parallel test processes do not collide.
     */
    public function getTempFilePath(
        string $originalFilePath,
        bool $randomizeName = false,
    ): string {
        $file = $this->getFile($originalFilePath);
        Assert::notFalse(
            value: $file,
            message: 'No file at the given path: ' . $originalFilePath,
        );

        $pathInfo = pathinfo($originalFilePath);
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        $name = $randomizeName
            ? $pathInfo['filename'] . '-' . uniqid(
                prefix: '',
                more_entropy: true,
            ) . $extension
            : $pathInfo['filename'] . $extension;

        $pid = getmypid();
        Assert::notFalse(value: $pid, message: 'Failed to get process ID.');

        $tempFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $pid . DIRECTORY_SEPARATOR . $name;

        $dir = dirname($tempFilePath);
        if (! is_dir($dir) && ! mkdir(
            directory: $dir,
            permissions: 0777,
            recursive: true,
        ) && ! is_dir(
            $dir,
        )) {
            throw new RuntimeException(sprintf(
                'Directory "%s" was not created.',
                $dir,
            ));
        }

        copy(from: $file, to: $tempFilePath);

        return $tempFilePath;
    }

    public function upload(
        string $originalFilePath,
        bool $randomizeName = false,
    ): UploadedFile {
        return self::createUploadedFileFromTmpFile(
            tmpFile: $this->getTempFilePath(
                originalFilePath: $originalFilePath,
                randomizeName: $randomizeName,
            ),
        );
    }

    public function getTestImage(bool $asJpeg = false): UploadedFile
    {
        $path = $asJpeg
            ? 'assets/images/fixtures/puppy-1.jpg'
            : 'assets/images/fixtures/avatar.png';

        return $this->upload($path);
    }

    /**
     * A random file from the fixture pool, for factories that want variety rather than
     * the same picture on every row.
     */
    public function getRandomImage(): UploadedFile
    {
        $directory = 'assets/images/fixtures/';
        $files = scandir($this->projectDir . '/' . $directory);
        Assert::notFalse(value: $files, message: 'There are no files here');

        $images = array_values(array_filter(
            array: array_diff($files, ['.', '..']),
            callback: static fn (string $file): bool => pathinfo(
                path: $directory . $file,
                flags: PATHINFO_EXTENSION,
            ) !== '',
        ));
        Assert::notEmpty(
            value: $images,
            message: 'No fixture images found in: ' . $directory,
        );

        return $this->upload(
            originalFilePath: $directory . $images[array_rand($images)],
            randomizeName: true,
        );
    }

    public function getFile(string $path): string|false
    {
        return realpath($this->projectDir . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * A path into the gendered avatar pool, for factories that want faces rather than
     * the same picture on every row. Diverse draws from the same pool as female.
     */
    public function getRandomAvatarPath(Gender $gender): string
    {
        $subdirectory = match ($gender) {
            Gender::Female, Gender::Diverse => 'female',
            default => 'male',
        };

        $directory = 'assets/images/fixtures/avatars/' . $subdirectory . '/';
        $fullPath = $this->projectDir . '/' . $directory;

        $files = scandir($fullPath);
        Assert::notFalse(
            value: $files,
            message: sprintf('Avatar directory not found: %s', $fullPath),
        );

        $images = array_values(array_diff($files, ['.', '..']));
        Assert::notEmpty(
            value: $images,
            message: sprintf('No avatar images found in: %s', $fullPath),
        );

        return $directory . $images[array_rand($images)];
    }
}
