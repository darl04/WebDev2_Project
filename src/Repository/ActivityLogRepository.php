<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function add(ActivityLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    
    /**
     * @return ActivityLog[]
     */
    public function findProductAdditionHistory(array $actions = ['CREATE', 'UPDATE'], int $limit = 25): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.action IN (:actions)')
            ->andWhere('a.target LIKE :targetPrefix')
            ->setParameter('actions', $actions)
            ->setParameter('targetPrefix', 'Products%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Generic entity history lookup by short class name (e.g. 'Rental', 'Products')
     *
     * @return ActivityLog[]
     */
    public function findEntityHistory(string $entityShortName, array $actions = ['CREATE', 'UPDATE'], int $limit = 25): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.action IN (:actions)')
            ->andWhere('a.target LIKE :targetPrefix')
            ->setParameter('actions', $actions)
            ->setParameter('targetPrefix', $entityShortName.'%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function remove(ActivityLog $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
