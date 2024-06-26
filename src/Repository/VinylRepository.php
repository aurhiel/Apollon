<?php

namespace App\Repository;

use App\Entity\Vinyl;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function findRequired(int $id): Vinyl
    {
        $entity = $this->findOneById($id);
        if (null === $entity) {
            throw new NotFoundHttpException(sprintf('Vinyl [id: %d] not found', $id));
        }

        return $entity;
    }

    public function findAll(bool $onlyAvailableForSelling = true)
    {
        $qb = $this->createQueryBuilder('v')
            ->leftJoin('v.artists', 'artists')
            ->addSelect('artists')
            ->leftJoin('v.images', 'images')
            ->addSelect('images')
            ->leftJoin('v.samples', 'samples')
            ->addSelect('samples')
        ;

        if (true === $onlyAvailableForSelling) {
            $qb->andWhere('v.quantity - v.quantitySold > 0');
        }

        return $qb->orderBy('artists.name', 'ASC')
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

    public function countVinylsWithCoverSold()
    {
        return $this->createQueryBuilder('v')
            ->select('SUM(CASE WHEN v.quantityWithCover > 0 AND v.quantityWithCover < v.quantitySold THEN v.quantityWithCover ELSE v.quantitySold END) AS nb_vinyls_cover_sold')
            ->where('v.quantityWithCover > 0')
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
            ->select('SUM(v.quantityWithCover) AS nb_vinyls_cover')
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
}
