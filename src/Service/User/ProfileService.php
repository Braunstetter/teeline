<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;

final readonly class ProfileService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * The picture goes with the account: the relation cascades and the entity listener
     * on Upload takes the file off the storage.
     */
    public function remove(User $user): void
    {
        $this->userRepository->remove(entity: $user, flush: true);
    }
}
