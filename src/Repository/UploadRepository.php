<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Upload\Upload;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Upload>
 */
final class UploadRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct(registry: $registry, entityClass: Upload::class);
    }

    /**
     * Every filename that could collide with the given name, in one query -- asking per
     * candidate costs one round trip per name already taken.
     *
     * @return list<string>
     */
    public function findFilenamesStartingWith(string $prefix): array
    {
        /** @var list<string> $filenames */
        $filenames = $this->createQueryBuilder('upload')
            ->select('upload.filename')
            ->andWhere('upload.filename LIKE :prefix')
            ->setParameter(
                key: 'prefix',
                value: addcslashes(string: $prefix, characters: '%_\\') . '%',
            )
            ->getQuery()
            ->getSingleColumnResult();

        return $filenames;
    }
}
