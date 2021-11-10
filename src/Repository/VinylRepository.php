<?php

namespace App\Repository;

use App\Entity\Vinyl;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Vinyl|null find($id, $lockMode = null, $lockVersion = null)
 * @method Vinyl|null findOneBy(array $criteria, array $orderBy = null)
 * @method Vinyl[]    findAll()
 * @method Vinyl[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VinylRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vinyl::class);
    }

    public function findAll()
    {
        return $this->createQueryBuilder('v')
            // Join relations
            ->leftJoin('v.artists', 'artists')
            ->addSelect('artists')
            // Order
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findAllAvailableForSale()
    {
        return $this->createQueryBuilder('v')
            // ->select('(v.quantity - v.quantitySold) AS qty_available')
            // Join relations
            ->leftJoin('v.artists', 'artists')
            ->addSelect('artists')
            ->leftJoin('v.inSales', 'isa')
            ->addSelect('isa')
            // Where
            ->andWhere('(v.quantity - v.quantitySold) > 0')
            // NOTE not working, done in twig
            // ->andWhere('isa.quantity IS NULL OR (v.quantity - isa.quantity) > 0')
            // Order
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function countVinylsSold()
    {
        return $this->createQueryBuilder('v')
            ->select('SUM(v.quantitySold) AS nb_vinyls_sold')
            ->getQuery()->getSingleScalarResult()
        ;
    }

    public function countAll()
    {
        return $this->createQueryBuilder('v')
            ->select('SUM(v.quantity) AS nb_vinyls')
            ->getQuery()->getSingleScalarResult()
        ;
    }

    public function countAllWithCover()
    {
        return $this->createQueryBuilder('v')
            ->select('SUM(v.quantityWithCover - v.quantitySold) AS nb_vinyls_cover')
            ->getQuery()->getSingleScalarResult()
        ;
    }

    public function countAllByArtist($artist)
    {
        return $this->createQueryBuilder('v')
            ->select('SUM(v.quantity) AS nb_vinyls')
            ->join('v.artists', 'a')
            ->where('a.id = :id_artist')
            ->setParameter('id_artist', $artist->getId())
            ->groupBy('a.id')
            ->getQuery()->getSingleScalarResult()
        ;
    }

    public function resetDatabase()
    {
        return $this->createQueryBuilder('v')
            ->delete(Vinyl::class, 'v')
            ->getQuery()
            ->getResult()
        ;
    }

    // /**
    //  * @return Vinyl[] Returns an array of Vinyl objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Vinyl
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
