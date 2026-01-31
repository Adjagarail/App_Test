<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Liste des utilisateurs avec recherche et pagination
     * GET /api/admin/users?search=&page=1&limit=10
     */
    #[Route('/users', name: 'api_admin_users', methods: ['GET'])]
    public function getUsers(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));
        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
           ->from(User::class, 'u');

        if ($search) {
            $qb->where('u.email LIKE :search OR u.NomComplet LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Total count
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Paginated results
        $users = $qb->orderBy('u.id', 'DESC')
                    ->setFirstResult($offset)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();

        $usersData = array_map(fn(User $user) => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nomComplet' => $user->getNomComplet(),
            'roles' => $user->getRoles()
        ], $users);

        return new JsonResponse([
            'users' => $usersData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'totalPages' => (int) ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Modifier les rôles d'un utilisateur
     * PUT /api/admin/users/{id}/roles
     */
    #[Route('/users/{id}/roles', name: 'api_admin_update_roles', methods: ['PUT'])]
    public function updateUserRoles(int $id, Request $request): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Empêcher la modification de son propre compte
        if ($user->getId() === $this->getUser()->getId()) {
            return new JsonResponse(['error' => 'Vous ne pouvez pas modifier vos propres rôles'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $roles = $data['roles'] ?? [];

        // Valider les rôles
        $allowedRoles = ['ROLE_USER', 'ROLE_SEMI_ADMIN', 'ROLE_ADMIN', 'ROLE_MODERATOR', 'ROLE_ANALYST'];
        $validRoles = array_filter($roles, fn($role) => in_array($role, $allowedRoles));

        // ROLE_USER est toujours présent (géré par l'entité)
        $user->setRoles(array_values(array_unique($validRoles)));
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Rôles mis à jour avec succès',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nomComplet' => $user->getNomComplet(),
                'roles' => $user->getRoles()
            ]
        ]);
    }

    /**
     * Obtenir les rôles disponibles
     * GET /api/admin/roles
     */
    #[Route('/roles', name: 'api_admin_roles', methods: ['GET'])]
    public function getAvailableRoles(): JsonResponse
    {
        return new JsonResponse([
            'roles' => [
                ['code' => 'ROLE_USER', 'label' => 'Utilisateur', 'description' => 'Accès basique à l\'application'],
                ['code' => 'ROLE_SEMI_ADMIN', 'label' => 'Semi-Admin', 'description' => 'Voir les stats mais pas gérer les users'],
                ['code' => 'ROLE_MODERATOR', 'label' => 'Modérateur', 'description' => 'Modérer le contenu utilisateur'],
                ['code' => 'ROLE_ANALYST', 'label' => 'Analyste', 'description' => 'Accès aux rapports et analytics'],
                ['code' => 'ROLE_ADMIN', 'label' => 'Administrateur', 'description' => 'Accès complet au système']
            ]
        ]);
    }
}
