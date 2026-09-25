<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailChangeRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\DatePoint;

/**
 * @psalm-suppress ClassMustBeFinal
 */
#[ORM\Entity(repositoryClass: EmailChangeRequestRepository::class)]
final class EmailChangeRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 180)]
        private string $newEmail,
        #[ORM\Column(type: 'date_point')]
        private DateTimeImmutable $expiresAt,
        #[ORM\Column(length: 64, unique: true)]
        private string $token,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getNewEmail(): string
    {
        return $this->newEmail;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function isExpired(): bool
    {
        return new DatePoint() > $this->expiresAt;
    }
}
