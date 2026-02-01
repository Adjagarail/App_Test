<?php

namespace App\Repository;

use App\Entity\AccountActionRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountActionRequest>
 */
class AccountActionRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountActionRequest::class);
    }

    public function findPendingByUserAndType(User $user, string $type): ?AccountActionRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.type = :type')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('status', AccountActionRequest::STATUS_PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array{items: AccountActionRequest[], total: int}
     */
    public function findPaginatedByStatusAndType(
        ?string $status = null,
        ?string $type = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($type !== null) {
            $qb->andWhere('r.type = :type')
                ->setParameter('type', $type);
        }

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    public function save(AccountActionRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AccountActionRequest $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
