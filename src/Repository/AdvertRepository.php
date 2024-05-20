<?php

namespace App\Repository;

use App\Entity\Advert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Advert|null find($id, $lockMode = null, $lockVersion = null)
 * @method Advert|null findOneBy(array $criteria, array $orderBy = null)
 * @method Advert[]    findAll()
 * @method Advert[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AdvertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Advert::class);
    }

    public function findAll()
    {
        return $this->createQueryBuilder('a')
            // Join relations
            ->leftJoin('a.images', 'images')
            ->addSelect('images')
            ->leftJoin('a.inSales', 'in_sales')
            ->addSelect('in_sales')
            ->leftJoin('in_sales.vinyl', 'vinyl')
            ->addSelect('vinyl')
            ->leftJoin('vinyl.artists', 'artists')
            ->addSelect('artists')
            ->orderBy('a.id', 'ASC')
            // Get query & result
            ->getQuery()
            ->getResult()
        ;
    }

    public function countAllSold()
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->where('a.is_sold = true')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countTotalPrices()
    {
        return $this->createQueryBuilder('a')
            ->select('SUM(a.price) AS total_price')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countTotalPricesCheckout()
    {
        return $this->createQueryBuilder('a')
            ->select('SUM(a.price) AS total_price')
            ->andWhere('a.is_sold = true')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
