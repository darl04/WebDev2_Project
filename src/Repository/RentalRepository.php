<?php

namespace App\Repository;

use App\Entity\Rental;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;
use DateTimeZone;

/**
 * @extends ServiceEntityRepository<Rental>
 */
class RentalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rental::class);
    }

    public function find(mixed $id, $lockMode = null, $lockVersion = null): ?Rental
    {
        $this->syncReturnedRentals();

        return parent::find($id, $lockMode, $lockVersion);
    }

    public function findAll(): array
    {
        $this->syncReturnedRentals();

        return parent::findAll();
    }

    private function syncReturnedRentals(): int
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(date_default_timezone_get() ?: 'UTC'));

        $rentals = $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.returnDate IS NOT NULL')
            ->andWhere('r.returnDate <= :today')
            ->setParameter('status', 'Rented')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        if ($rentals === []) {
            return 0;
        }

        foreach ($rentals as $rental) {
            $rental->setStatus('Returned');
        }

        $this->getEntityManager()->flush();

        return count($rentals);
    }
}

