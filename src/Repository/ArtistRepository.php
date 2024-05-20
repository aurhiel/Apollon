<?php

namespace App\Repository;

use App\Entity\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Artist|null find($id, $lockMode = null, $lockVersion = null)
 * @method Artist|null findOneBy(array $criteria, array $orderBy = null)
 * @method Artist[]    findAll()
 * @method Artist[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artist::class);
    }

    public function findAll()
    {
        return $this->createQueryBuilder('a')
            // Join relations
            ->leftJoin('a.vinyls', 'vinyls')
            ->addSelect('vinyls')
            // Order
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneById($id): ?Artist
    {
        return $this->createQueryBuilder('a')
            // Join relations
            ->leftJoin('a.vinyls', 'vinyls')
            ->addSelect('vinyls')
            ->leftJoin('vinyls.artists', 'artists')
            ->addSelect('artists')
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function resetDatabase()
    {
        return $this->createQueryBuilder('a')
            ->delete(Artist::class, 'a')
            ->getQuery()
            ->getResult()
        ;
    }
}
