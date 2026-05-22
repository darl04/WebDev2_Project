<?php

namespace App\Repository;

use App\Entity\Stock;
use App\Entity\Products;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    /**
     * Find stock entry for a specific product
     */
    public function findOneByProduct(Products $product): ?Stock
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

