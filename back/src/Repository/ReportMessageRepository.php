<?php

namespace App\Repository;

use App\Entity\ReportMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReportMessage>
 */
class ReportMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportMessage::class);
    }

    public function save(ReportMessage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReportMessage $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get all threads (messages without a parent) with pagination.
     */
    public function findThreads(int $page = 1, int $limit = 20, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.parentId IS NULL')
            ->orderBy('r.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Count threads (for pagination).
     */
    public function countThreads(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.parentId IS NULL');

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get all messages in a thread.
     */
    public function findByThread(int $threadId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.threadId = :threadId OR r.id = :threadId')
            ->setParameter('threadId', $threadId)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get threads for a specific user.
     */
    public function findUserThreads(User $user, int $page = 1, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.sender = :user')
            ->andWhere('r.parentId IS NULL')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread messages for admins.
     */
    public function countUnreadForAdmins(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.isRead = false')
            ->andWhere('r.type = :type')
            ->setParameter('type', ReportMessage::TYPE_USER_MESSAGE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count unread messages for a user.
     */
    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.isRead = false')
            ->andWhere('r.type = :type')
            ->andWhere('r.threadId IN (
                SELECT DISTINCT COALESCE(r2.threadId, r2.id)
                FROM App\Entity\ReportMessage r2
                WHERE r2.sender = :user
            )')
            ->setParameter('type', ReportMessage::TYPE_ADMIN_RESPONSE)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
