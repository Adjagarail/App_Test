<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return array{items: Notification[], total: int}
     */
    public function findPaginatedByRecipient(
        User $recipient,
        int $page = 1,
        int $limit = 20,
        ?bool $unreadOnly = null
    ): array {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('n.createdAt', 'DESC');

        if ($unreadOnly === true) {
            $qb->andWhere('n.isRead = :isRead')
                ->setParameter('isRead', false);
        } elseif ($unreadOnly === false) {
            $qb->andWhere('n.isRead = :isRead')
                ->setParameter('isRead', true);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    public function countUnreadByRecipient(User $recipient): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('recipient', $recipient)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllAsReadByRecipient(User $recipient): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->set('n.readAt', ':readAt')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :unread')
            ->setParameter('isRead', true)
            ->setParameter('readAt', new \DateTimeImmutable())
            ->setParameter('recipient', $recipient)
            ->setParameter('unread', false)
            ->getQuery()
            ->execute();
    }

    /**
     * Find notifications for a user for data export.
     *
     * @return Notification[]
     */
    public function findByRecipient(User $recipient, int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete old read notifications (cleanup).
     */
    public function deleteOldReadNotifications(int $daysOld = 30): int
    {
        $threshold = new \DateTimeImmutable("-{$daysOld} days");

        return $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.isRead = :isRead')
            ->andWhere('n.readAt < :threshold')
            ->setParameter('isRead', true)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    public function save(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
