<?php

declare(strict_types=1);

namespace App\Entity\Upload;

use App\Repository\UploadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Stringable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @psalm-suppress PropertyNotSetInConstructor, ClassMustBeFinal
 */
#[ORM\Table(name: 'upload')]
#[ORM\Entity(repositoryClass: UploadRepository::class)]
class Upload implements UploadInterface, Stringable
{
    public const string MAX_UPLOAD_SIZE = '50M';

    public const array ALLOWED_MIME_TYPES = [
        'audio/mpeg',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'video/mp4',
    ];

    public const array ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    protected ?Ulid $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $filename = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $originalFilename = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $mimeType = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $dimensions = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[Assert\File(
        maxSize: self::MAX_UPLOAD_SIZE,
        mimeTypes: self::ALLOWED_MIME_TYPES,
        maxSizeMessage: 'upload.maxSizeMessage',
        mimeTypesMessage: 'upload.mimeTypesMessage',
    )]
    private ?File $file = null;

    public function __construct(
        ?File $file = null,
    ) {
        $this->setFile($file);
        $this->id = new Ulid();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getFilename() ?? '';
    }

    /**
     * The uploaded file never belongs in a session. It only lives here between the form
     * submission and the move onto the storage -- and when validation rejects it, it is
     * still attached while Symfony serialises the user into the session.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = get_object_vars($this);
        unset($data['file']);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public static function getAcceptAttribute(): string
    {
        return implode(separator: ',', array: self::ALLOWED_MIME_TYPES);
    }

    #[Override]
    public function getId(): ?Ulid
    {
        return $this->id;
    }

    #[Override]
    public function setId(?Ulid $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[Override]
    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename ?? null;
    }

    #[Override]
    public function setOriginalFilename(?string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    #[Override]
    public function getFile(): ?File
    {
        return $this->file;
    }

    #[Override]
    public function setFile(?File $file): self
    {
        $this->file = $file;
        return $this;
    }

    #[Override]
    public function hasFile(): bool
    {
        return $this->file instanceof File;
    }

    #[Override]
    public function hasFilename(): bool
    {
        return $this->getFilename() !== null;
    }

    #[Override]
    public function getFilename(): ?string
    {
        return $this->filename ?? null;
    }

    #[Override]
    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    #[Override]
    public function size(): ?int
    {
        return $this->size;
    }

    #[Override]
    public function setSize(?int $size): UploadInterface
    {
        $this->size = $size;
        return $this;
    }

    #[Override]
    public function dimensions(): ?string
    {
        return $this->dimensions;
    }

    #[Override]
    public function setDimensions(?string $dimensions = null): UploadInterface
    {
        $this->dimensions = $dimensions;
        return $this;
    }

    #[Override]
    public function getFullPath(): ?string
    {
        return $this->filename;
    }

    #[Override]
    public function isImage(): bool
    {
        return str_starts_with(
            haystack: $this->getMimeType() ?? '',
            needle: 'image/',
        );
    }

    #[Override]
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    #[Override]
    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function isAudio(): bool
    {
        return str_starts_with(
            haystack: $this->getMimeType() ?? '',
            needle: 'audio/',
        );
    }

    public function isVideo(): bool
    {
        return str_starts_with(
            haystack: $this->getMimeType() ?? '',
            needle: 'video/',
        );
    }

    /**
     * @return list<Constraint>
     */
    public static function getFileConstraints(): array
    {
        return [
            new Assert\File(
                maxSize: self::MAX_UPLOAD_SIZE,
                mimeTypes: self::ALLOWED_MIME_TYPES,
                maxSizeMessage: 'upload.maxSizeMessage',
                mimeTypesMessage: 'upload.mimeTypesMessage',
            ),
        ];
    }
}
