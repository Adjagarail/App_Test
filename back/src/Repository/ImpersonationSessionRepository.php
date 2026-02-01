<?php

namespace App\Repository;

use App\Entity\ImpersonationSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImpersonationSession>
 */
class ImpersonationSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImpersonationSession::class);
    }

    public function findActiveByImpersonator(User $impersonator): ?ImpersonationSession
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.impersonator = :impersonator')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('impersonator', $impersonator)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTokenJti(string $jti): ?ImpersonationSession
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.tokenJti = :jti')
            ->setParameter('jti', $jti)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveByTokenJti(string $jti): ?ImpersonationSession
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.tokenJti = :jti')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('jti', $jti)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Revoke all active sessions for an impersonator.
     */
    public function revokeAllActiveByImpersonator(User $impersonator): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.revokedAt', ':revokedAt')
            ->andWhere('s.impersonator = :impersonator')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('revokedAt', new \DateTimeImmutable())
            ->setParameter('impersonator', $impersonator)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete expired sessions (cleanup).
     */
    public function deleteExpiredSessions(int $daysOld = 7): int
    {
        $threshold = new \DateTimeImmutable("-{$daysOld} days");

        return $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.expiresAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    public function save(ImpersonationSession $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ImpersonationSession $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
