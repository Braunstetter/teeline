<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailChangeRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailChangeRequest>
 */
final class EmailChangeRequestRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct(registry: $registry, entityClass: EmailChangeRequest::class);
    }

    public function findValidByToken(string $token): ?EmailChangeRequest
    {
        $request = $this->findOneBy([
            'token' => $token,
        ]);

        if (! $request instanceof EmailChangeRequest || $request->isExpired()) {
            return null;
        }

        return $request;
    }

    public function removeAllForUser(User $user): void
    {
        $this->createQueryBuilder('email_change_request')
            ->delete()
            ->where('email_change_request.user = :user')
            ->setParameter(key: 'user', value: $user->getId())
            ->getQuery()
            ->execute();
    }
}
