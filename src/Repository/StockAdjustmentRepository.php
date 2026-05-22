<?php

namespace App\Repository;

use App\Entity\Stock;
use App\Entity\StockAdjustment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockAdjustment>
 */
class StockAdjustmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockAdjustment::class);
    }

    /**
     * @return list<StockAdjustment>
     */
    public function findByStock(Stock $stock): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.stock = :stock')
            ->setParameter('stock', $stock)
            ->orderBy('sa.createdAt', 'DESC')
            ->addOrderBy('sa.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return StockAdjustment[]
     */
    public function findRecentAdjustments(int $limit = 25): array
    {
        return $this->createQueryBuilder('sa')
            ->orderBy('sa.createdAt', 'DESC')
            ->addOrderBy('sa.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}