<?php

namespace App\Repository;

use App\Entity\Sample;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method Sample|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sample|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sample[]    findAll()
 * @method Sample[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SampleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sample::class);
    }

    public function findRequired(int $id): Sample
    {
        $sample = $this->findOneById($id);
        if (null === $sample) {
            throw new NotFoundHttpException(sprintf('Sample [id: %d] not found', $id));
        }

        return $sample;
    }

    public function resetDatabase()
    {
        return $this->createQueryBuilder('s')
            ->delete(Sample::class, 's')
            ->getQuery()
            ->getResult()
        ;
    }
}
