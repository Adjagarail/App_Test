<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    public function save(UserSession $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserSession $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find active session by JWT ID.
     */
    public function findActiveByJti(string $jti): ?UserSession
    {
        return $this->createQueryBuilder('s')
            ->where('s.tokenJti = :jti')
            ->andWhere('s.endedAt IS NULL')
            ->setParameter('jti', $jti)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active sessions for a user.
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.endedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('s.lastSeenAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active sessions (users seen within X minutes).
     *
     * @param int $withinMinutes Default 5 minutes for "active" definition
     * @return array{activeUsers: int, activeSessions: int}
     */
    public function countActiveSessions(int $withinMinutes = 5): array
    {
        $threshold = new \DateTimeImmutable("-{$withinMinutes} minutes");

        // Count active sessions
        $sessionsCount = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.endedAt IS NULL')
            ->andWhere('s.lastSeenAt >= :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();

        // Count distinct active users
        $usersCount = (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.user)')
            ->where('s.endedAt IS NULL')
            ->andWhere('s.lastSeenAt >= :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'activeUsers' => $usersCount,
            'activeSessions' => $sessionsCount,
        ];
    }

    /**
     * End all active sessions for a user.
     */
    public function endAllUserSessions(User $user): int
    {
        return $this->createQueryBuilder('s')
            ->update()
            ->set('s.endedAt', ':now')
            ->where('s.user = :user')
            ->andWhere('s.endedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * End session by JWT ID.
     */
    public function endSessionByJti(string $jti): bool
    {
        $session = $this->findActiveByJti($jti);

        if ($session) {
            $session->end();
            $this->getEntityManager()->flush();
            return true;
        }

        return false;
    }

    /**
     * Update lastSeenAt for a session by JTI.
     * Uses rate limiting to avoid too frequent updates.
     *
     * @param int $minIntervalSeconds Minimum interval between updates (default 60s)
     */
    public function updateLastSeen(string $jti, int $minIntervalSeconds = 60): bool
    {
        $session = $this->findActiveByJti($jti);

        if (!$session) {
            return false;
        }

        // Rate limit updates
        $lastSeen = $session->getLastSeenAt();
        $now = new \DateTimeImmutable();

        if ($lastSeen && ($now->getTimestamp() - $lastSeen->getTimestamp()) < $minIntervalSeconds) {
            return false; // Skip update, too soon
        }

        $session->updateLastSeen();
        $this->getEntityManager()->flush();
        return true;
    }

    /**
     * Clean up old ended sessions (older than X days).
     */
    public function cleanupOldSessions(int $olderThanDays = 30): int
    {
        $threshold = new \DateTimeImmutable("-{$olderThanDays} days");

        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.endedAt IS NOT NULL')
            ->andWhere('s.endedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
