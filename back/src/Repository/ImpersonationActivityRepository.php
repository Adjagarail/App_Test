<?php

namespace App\Repository;

use App\Entity\ImpersonationActivity;
use App\Entity\ImpersonationSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImpersonationActivity>
 */
class ImpersonationActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImpersonationActivity::class);
    }

    public function save(ImpersonationActivity $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all activities for a session.
     *
     * @return ImpersonationActivity[]
     */
    public function findBySession(ImpersonationSession $session): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.session = :session')
            ->setParameter('session', $session)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activities for a session with pagination.
     */
    public function findBySessionPaginated(ImpersonationSession $session, int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.session = :session')
            ->setParameter('session', $session);

        $total = (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => (int) $total,
        ];
    }

    /**
     * Count activities for a session.
     */
    public function countBySession(ImpersonationSession $session): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.session = :session')
            ->setParameter('session', $session)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get unique paths visited during a session.
     *
     * @return string[]
     */
    public function getUniquePathsBySession(ImpersonationSession $session): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('DISTINCT a.path')
            ->andWhere('a.session = :session')
            ->andWhere('a.path IS NOT NULL')
            ->setParameter('session', $session)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'path');
    }

    /**
     * Get activity summary for a session.
     */
    public function getSessionSummary(ImpersonationSession $session): array
    {
        $activities = $this->findBySession($session);
        $paths = [];
        $apiCalls = [];
        $actions = [];

        foreach ($activities as $activity) {
            switch ($activity->getType()) {
                case ImpersonationActivity::TYPE_PAGE_VIEW:
                    $paths[] = $activity->getPath();
                    break;
                case ImpersonationActivity::TYPE_API_CALL:
                    $apiCalls[] = [
                        'method' => $activity->getMethod(),
                        'path' => $activity->getPath(),
                        'action' => $activity->getAction(),
                    ];
                    break;
                case ImpersonationActivity::TYPE_ACTION:
                    $actions[] = $activity->getAction();
                    break;
            }
        }

        return [
            'totalActivities' => count($activities),
            'pagesVisited' => array_unique($paths),
            'apiCalls' => $apiCalls,
            'actions' => $actions,
        ];
    }
}
