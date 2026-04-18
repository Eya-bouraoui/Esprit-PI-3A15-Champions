<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    
    public function findByFilters(?string $userName, ?string $type): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.walletSource', 'ws')
            ->leftJoin('ws.utilisateur', 'u')
            ->leftJoin('t.creditCard', 'cc')
            ->leftJoin('cc.utilisateur', 'u2');

        if ($userName) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'CONCAT(u.prenom, \' \', u.nom) LIKE :name',
                    'CONCAT(u.nom, \' \', u.prenom) LIKE :name',
                    'CONCAT(u2.prenom, \' \', u2.nom) LIKE :name',
                    'CONCAT(u2.nom, \' \', u2.prenom) LIKE :name'
                )
            )->setParameter('name', '%' . $userName . '%');
        }

        if ($type) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        return $qb->orderBy('t.dateTransaction', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}