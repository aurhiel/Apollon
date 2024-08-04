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

    public function findAll(bool $onlyAvailable = true)
    {
        $qb = $this->createQueryBuilder('advert')
            ->select('advert, images, in_sales, vinyl, artists')
            ->leftJoin('advert.images', 'images')
            ->leftJoin('advert.inSales', 'in_sales')
            ->leftJoin('in_sales.vinyl', 'vinyl')
            ->leftJoin('vinyl.artists', 'artists')
        ;

        if (true === $onlyAvailable) {
            $qb->where('advert.isSold = false');
        }

        return $qb->orderBy('advert.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function countAllSold()
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->where('a.isSold = true')
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
            ->andWhere('a.isSold = true')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
