<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Webmozart\Assert\Assert;

/**
 * @extends ServiceEntityRepository<User>
 */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct(registry: $registry, entityClass: User::class);
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        $user = $this->createQueryBuilder('user')
            ->andWhere('user.email = :email')
            ->andWhere('user.isVerified = :verified')
            ->setParameter(key: 'email', value: $identifier)
            ->setParameter(key: 'verified', value: true)
            ->getQuery()
            ->getOneOrNullResult();

        Assert::nullOrIsInstanceOf(value: $user, class: User::class);

        return $user;
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    #[Override]
    public function upgradePassword(
        PasswordAuthenticatedUserInterface $user,
        string $newHashedPassword,
    ): void {
        if (! $user instanceof User) {
            throw new UnsupportedUserException(sprintf(
                'Instances of "%s" are not supported.',
                $user::class,
            ));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()
            ->persist($user);
        $this->getEntityManager()
            ->flush();
    }
}
