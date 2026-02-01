<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active (non-deleted) users with a specific role.
     * Uses native SQL for PostgreSQL JSON compatibility.
     *
     * @return User[]
     */
    public function findActiveByRole(string $role): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT id FROM "user"
            WHERE deleted_at IS NULL
            AND roles::text LIKE :role
        ';

        $result = $conn->executeQuery($sql, [
            'role' => '%' . $role . '%',
        ]);

        $ids = array_column($result->fetchAllAssociative(), 'id');

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active admins (for protection against removing last admin).
     * Uses native SQL for PostgreSQL JSON compatibility.
     */
    public function countActiveAdmins(): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT COUNT(id) as count FROM "user"
            WHERE deleted_at IS NULL
            AND roles::text LIKE :role
        ';

        $result = $conn->executeQuery($sql, [
            'role' => '%ROLE_ADMIN%',
        ]);

        return (int) $result->fetchOne();
    }

    /**
     * Feature 9: Paginated user list with search, filters, and sorting.
     *
     * @return array{items: User[], total: int}
     */
    public function findPaginatedWithFilters(
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?string $role = null,
        ?string $status = null,
        string $sort = 'dateInscription',
        string $direction = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('u');

        // Filter by status
        switch ($status) {
            case 'active':
                $qb->andWhere('u.deletedAt IS NULL')
                    ->andWhere('u.isSuspended = false')
                    ->andWhere('u.isEmailVerified = true');
                break;
            case 'suspended':
                $qb->andWhere('u.deletedAt IS NULL')
                    ->andWhere('u.isSuspended = true');
                break;
            case 'deleted':
                $qb->andWhere('u.deletedAt IS NOT NULL');
                break;
            case 'unverified':
                $qb->andWhere('u.deletedAt IS NULL')
                    ->andWhere('u.isEmailVerified = false');
                break;
            default:
                // No status filter - include all users including deleted
                break;
        }

        // Search by email or name
        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(u.email) LIKE :search OR LOWER(u.NomComplet) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        // Filter by role (using native SQL for PostgreSQL JSON compatibility)
        if ($role !== null && $role !== '') {
            $conn = $this->getEntityManager()->getConnection();
            $sql = 'SELECT id FROM "user" WHERE roles::text LIKE :role';
            $result = $conn->executeQuery($sql, ['role' => '%' . $role . '%']);
            $roleUserIds = array_column($result->fetchAllAssociative(), 'id');

            if (empty($roleUserIds)) {
                // No users with this role, return empty result
                return ['items' => [], 'total' => 0];
            }

            $qb->andWhere('u.id IN (:roleUserIds)')
                ->setParameter('roleUserIds', $roleUserIds);
        }

        // Sorting
        $sortColumn = match ($sort) {
            'dateDerniereConnexion', 'lastLogin' => 'u.dateDerniereConnexion',
            'nomComplet', 'name' => 'u.NomComplet',
            'email' => 'u.email',
            'dateInscription', 'createdAt' => 'u.dateInscription',
            default => 'u.dateInscription',
        };

        $sortDirection = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($sortColumn, $sortDirection);

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * Find users to purge: soft deleted for X days OR inactive for X months.
     *
     * @return User[]
     */
    public function findUsersToPurge(int $softDeletedDays = 30, int $inactiveMonths = 24): array
    {
        $softDeleteThreshold = new \DateTimeImmutable("-{$softDeletedDays} days");
        $inactiveThreshold = new \DateTimeImmutable("-{$inactiveMonths} months");

        return $this->createQueryBuilder('u')
            ->andWhere(
                '(u.deletedAt IS NOT NULL AND u.deletedAt < :softDeleteThreshold) OR ' .
                '(u.dateDerniereConnexion IS NOT NULL AND u.dateDerniereConnexion < :inactiveThreshold)'
            )
            ->setParameter('softDeleteThreshold', $softDeleteThreshold)
            ->setParameter('inactiveThreshold', $inactiveThreshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total active users (non-deleted).
     */
    public function countActiveUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users who logged in today.
     */
    public function countTodayLogins(): int
    {
        $today = new \DateTimeImmutable('today');

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.dateDerniereConnexion >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all admins (ROLE_ADMIN or ROLE_SUPER_ADMIN) who are not deleted.
     * Uses native SQL for PostgreSQL JSON compatibility.
     *
     * @return User[]
     */
    public function findAdmins(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT id FROM "user"
            WHERE deleted_at IS NULL
            AND (roles::text LIKE :roleAdmin OR roles::text LIKE :roleSuperAdmin)
        ';

        $result = $conn->executeQuery($sql, [
            'roleAdmin' => '%ROLE_ADMIN%',
            'roleSuperAdmin' => '%ROLE_SUPER_ADMIN%',
        ]);

        $ids = array_column($result->fetchAllAssociative(), 'id');

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
