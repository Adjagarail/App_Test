<?php

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @return array{items: AuditLog[], total: int}
     */
    public function findPaginatedByUser(
        User $user,
        int $page = 1,
        int $limit = 20,
        ?string $action = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.actorUser = :user OR a.targetUser = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC');

        if ($action !== null) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * @return array{items: AuditLog[], total: int}
     */
    public function findPaginatedByTargetUser(
        User $user,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.targetUser = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * Find audit logs for a user (as actor) for data export.
     *
     * @return AuditLog[]
     */
    public function findByActorUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.actorUser = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all audit logs with filters and pagination.
     *
     * @return array{items: AuditLog[], total: int}
     */
    public function findAllPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $action = null,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.actorUser', 'actor')
            ->leftJoin('a.targetUser', 'target')
            ->orderBy('a.createdAt', 'DESC');

        if ($action !== null) {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('actor.email LIKE :search OR target.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * Get available action types for filter.
     *
     * @return string[]
     */
    public function getDistinctActions(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.action')
            ->orderBy('a.action', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'action');
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
