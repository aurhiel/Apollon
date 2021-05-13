<?php

namespace App\Repository;

use App\Entity\InSale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method InSale|null find($id, $lockMode = null, $lockVersion = null)
 * @method InSale|null findOneBy(array $criteria, array $orderBy = null)
 * @method InSale[]    findAll()
 * @method InSale[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InSaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InSale::class);
    }

    public function countVinylsInSale()
    {
        return $this->createQueryBuilder('i')
            ->select('SUM(i.quantity) AS nb_vinyls_in_sale')
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // /**
    //  * @return InSale[] Returns an array of InSale objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?InSale
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
