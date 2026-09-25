<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Upload\Upload;
use App\Service\FileHelper;
use App\Service\Upload\UploadService;
use Override;
use Symfony\Component\Uid\Ulid;
use Webmozart\Assert\Assert;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Upload>
 */
final class UploadFactory extends PersistentObjectFactory
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly FileHelper $fileHelper,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function class(): string
    {
        return Upload::class;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function defaults(): array
    {
        return [
            'id' => new Ulid(),
        ];
    }

    #[Override]
    protected function initialize(): static
    {
        return $this
            ->instantiateWith(function (array $attributes): Upload {
                $upload = new Upload($this->fileHelper->getRandomImage());

                if (isset($attributes['file'])) {
                    $path = $attributes['file'];
                    Assert::string($path);

                    $upload->setFile($this->fileHelper->upload($path));
                }

                return $upload;
            })
            ->afterInstantiate(function (Upload $upload): void {
                $this->uploadService->processUploadActions($upload);
            });
    }
}
