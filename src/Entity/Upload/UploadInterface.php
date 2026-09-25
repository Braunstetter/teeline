<?php

declare(strict_types=1);

namespace App\Entity\Upload;

use SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Ulid;

interface UploadInterface
{
    public function getId(): ?Ulid;

    public function setId(?Ulid $id): self;

    public function getFilename(): ?string;

    public function getOriginalFilename(): ?string;

    public function getFile(): ?SplFileInfo;

    public function getMimeType(): ?string;

    public function hasFile(): bool;

    public function hasFilename(): bool;

    public function setFilename(string $filename): self;

    public function setOriginalFilename(string $originalFilename): self;

    public function setFile(?File $file): self;

    public function setMimeType(string|null $mimeType): self;

    public function dimensions(): ?string;

    public function setDimensions(?string $dimensions = null): self;

    public function size(): ?int;

    public function setSize(?int $size): self;

    public function getFullPath(): ?string;

    public function isImage(): bool;
}
