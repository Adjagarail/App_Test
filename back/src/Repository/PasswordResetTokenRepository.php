<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findValidToken(string $token): ?PasswordResetToken
    {
        return $this->createQueryBuilder('p')
            ->where('p.token = :token')
            ->andWhere('p.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    public function deleteTokensForUser(int $userId): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
