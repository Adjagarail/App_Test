<?php

namespace App\Controller;

use App\Entity\AccountActionRequest;
use App\Entity\User;
use App\Repository\AccountActionRequestRepository;
use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\NotificationService;
use App\Service\SessionService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly AuditService $auditService,
        private readonly NotificationService $notificationService,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly SessionService $sessionService
    ) {
    }

    /**
     * Get active users metrics.
     * GET /api/admin/metrics/active-users
     *
     * Returns:
     * - activeUsers: number of distinct users with active sessions
     * - activeSessions: total number of active sessions
     * - updatedAt: timestamp of the query
     * - activeDefinition: explanation of "active" criteria (default: seen within 5 minutes)
     */
    #[Route('/metrics/active-users', name: 'api_admin_metrics_active_users', methods: ['GET'])]
    public function getActiveUsersMetrics(Request $request): JsonResponse
    {
        $withinMinutes = $request->query->getInt('minutes', SessionService::DEFAULT_ACTIVE_MINUTES);

        // Clamp to reasonable values (1-60 minutes)
        $withinMinutes = max(1, min(60, $withinMinutes));

        $metrics = $this->sessionService->getActiveUsersMetrics($withinMinutes);

        return new JsonResponse($metrics);
    }

    /**
     * Feature 9: Liste des utilisateurs avec recherche, filtres, tri et pagination.
     * GET /api/admin/users?page&limit&search&role&status&sortBy&sortOrder
     */
    #[Route('/users', name: 'api_admin_users', methods: ['GET'])]
    public function getUsers(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $search = $request->query->get('q', $request->query->get('search', ''));
        $role = $request->query->get('role');
        $status = $request->query->get('status'); // active, suspended, deleted, unverified
        $sort = $request->query->get('sort', $request->query->get('sortBy', 'dateInscription'));
        $direction = $request->query->get('direction', $request->query->get('sortOrder', 'DESC'));

        $result = $this->userRepository->findPaginatedWithFilters(
            $page,
            $limit,
            $search ?: null,
            $role,
            $status,
            $sort,
            $direction
        );

        $usersData = array_map(fn(User $user) => $this->serializeUserForAdmin($user), $result['items']);

        return new JsonResponse([
            'users' => $usersData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    /**
     * Feature 1: Profil admin d'un utilisateur.
     * GET /api/admin/users/{id}
     */
    #[Route('/users/{id}', name: 'api_admin_user_profile', methods: ['GET'])]
    public function getUserProfile(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'user' => $this->serializeUserForAdmin($user),
        ]);
    }

    /**
     * Feature 1 + 6: Suppression d'un utilisateur (soft delete).
     * DELETE /api/admin/users/{id}
     */
    #[Route('/users/{id}', name: 'api_admin_delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        // Cannot delete yourself
        if ($user->getId() === $admin->getId()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_DELETE_SELF',
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Protect last admin
        if ($user->hasRole('ROLE_ADMIN') && $this->userRepository->countActiveAdmins() <= 1) {
            return new JsonResponse([
                'error' => [
                    'code' => 'LAST_ADMIN',
                    'message' => 'Impossible de supprimer le dernier administrateur',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Soft delete
        $user->softDelete();
        $this->entityManager->flush();

        // Revoke all refresh tokens
        $this->revokeUserTokens($user);

        // Audit
        $this->auditService->logSoftDelete($admin, $user);

        return new JsonResponse([
            'message' => 'Utilisateur supprimé avec succès',
        ]);
    }

    /**
     * Feature 6: Restaurer un utilisateur supprimé (soft delete).
     * PATCH /api/admin/users/{id}/restore
     */
    #[Route('/users/{id}/restore', name: 'api_admin_restore_user', methods: ['PATCH'])]
    public function restoreUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isDeleted()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_DELETED',
                    'message' => 'Cet utilisateur n\'est pas supprimé',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $user->restore();
        $this->entityManager->flush();

        // Audit
        $this->auditService->logRestore($admin, $user);

        return new JsonResponse([
            'message' => 'Utilisateur restauré avec succès',
            'user' => $this->serializeUserForAdmin($user),
        ]);
    }

    /**
     * Feature 6: Supprimer définitivement un utilisateur (hard delete).
     * DELETE /api/admin/users/{id}/hard
     */
    #[Route('/users/{id}/hard', name: 'api_admin_hard_delete_user', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function hardDeleteUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        // Cannot delete yourself
        if ($user->getId() === $admin->getId()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_DELETE_SELF',
                    'message' => 'Vous ne pouvez pas vous supprimer vous-même',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Protect admins
        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN')) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_DELETE_ADMIN',
                    'message' => 'Impossible de supprimer définitivement un administrateur',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $userId = $user->getId();
        $email = $user->getEmail();

        // Revoke all tokens first
        $this->revokeUserTokens($user);

        // Audit before deletion
        $this->auditService->logHardDelete($admin, $userId, $email);

        // Hard delete
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Utilisateur supprimé définitivement',
        ]);
    }

    /**
     * Feature 5: Modifier les rôles d'un utilisateur.
     * PATCH /api/admin/users/{id}/roles
     */
    #[Route('/users/{id}/roles', name: 'api_admin_update_roles', methods: ['PATCH', 'PUT'])]
    public function updateUserRoles(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        // Cannot modify your own roles
        if ($user->getId() === $admin->getId()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_MODIFY_SELF',
                    'message' => 'Vous ne pouvez pas modifier vos propres rôles',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $roles = $data['roles'] ?? [];

        if (!is_array($roles)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'INVALID_ROLES',
                    'message' => 'Les rôles doivent être un tableau',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate roles
        $allowedRoles = ['ROLE_USER', 'ROLE_SEMI_ADMIN', 'ROLE_ADMIN', 'ROLE_MODERATOR', 'ROLE_ANALYST', 'ROLE_SUPER_ADMIN'];
        $validRoles = array_filter($roles, fn($role) => in_array($role, $allowedRoles));

        // Cannot grant ROLE_SUPER_ADMIN unless you are ROLE_SUPER_ADMIN
        if (in_array('ROLE_SUPER_ADMIN', $validRoles) && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_GRANT_SUPER_ADMIN',
                    'message' => 'Seul un Super Admin peut accorder ce rôle',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Protect last admin
        $oldRoles = $user->getRoles();
        $hadAdmin = in_array('ROLE_ADMIN', $oldRoles);
        $willHaveAdmin = in_array('ROLE_ADMIN', $validRoles);

        if ($hadAdmin && !$willHaveAdmin && $this->userRepository->countActiveAdmins() <= 1) {
            return new JsonResponse([
                'error' => [
                    'code' => 'LAST_ADMIN',
                    'message' => 'Impossible de retirer le rôle du dernier administrateur',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $user->setRoles(array_values(array_unique($validRoles)));
        $this->entityManager->flush();

        $newRoles = $user->getRoles();

        // Audit
        $this->auditService->logRolesUpdated($admin, $user, $oldRoles, $newRoles);

        // Notify user only if roles actually changed
        $rolesChanged = !empty(array_diff($oldRoles, $newRoles)) || !empty(array_diff($newRoles, $oldRoles));
        if ($rolesChanged) {
            $this->notificationService->notifyRolesUpdated($user, $newRoles);
        }

        return new JsonResponse([
            'message' => 'Rôles mis à jour avec succès',
            'user' => $this->serializeUserForAdmin($user),
        ]);
    }

    /**
     * Feature 11: Suspendre un utilisateur.
     * PATCH /api/admin/users/{id}/suspend
     */
    #[Route('/users/{id}/suspend', name: 'api_admin_suspend_user', methods: ['PATCH'])]
    public function suspendUser(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        if ($user->getId() === $admin->getId()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_SUSPEND_SELF',
                    'message' => 'Vous ne pouvez pas vous suspendre vous-même',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;
        $suspendedUntil = null;

        if (!empty($data['suspendedUntil'])) {
            try {
                $suspendedUntil = new \DateTimeImmutable($data['suspendedUntil']);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'error' => [
                        'code' => 'INVALID_DATE',
                        'message' => 'Date de fin de suspension invalide',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $user->suspend($reason, $suspendedUntil);
        $this->entityManager->flush();

        // Revoke tokens
        $this->revokeUserTokens($user);

        // Audit
        $this->auditService->logSuspended($admin, $user, $reason, $suspendedUntil);

        // Notify user
        $this->notificationService->notifyAccountSuspended($user, $reason, $suspendedUntil);

        return new JsonResponse([
            'message' => 'Utilisateur suspendu avec succès',
            'user' => $this->serializeUserForAdmin($user),
        ]);
    }

    /**
     * Feature 11: Réactiver un utilisateur suspendu.
     * PATCH /api/admin/users/{id}/unsuspend
     */
    #[Route('/users/{id}/unsuspend', name: 'api_admin_unsuspend_user', methods: ['PATCH'])]
    public function unsuspendUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isSuspended()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_SUSPENDED',
                    'message' => 'Cet utilisateur n\'est pas suspendu',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $user->unsuspend();
        $this->entityManager->flush();

        // Audit
        $this->auditService->logUnsuspended($admin, $user);

        // Notify user
        $this->notificationService->notifyAccountUnsuspended($user);

        return new JsonResponse([
            'message' => 'Utilisateur réactivé avec succès',
            'user' => $this->serializeUserForAdmin($user),
        ]);
    }

    /**
     * Feature 4: Historique d'audit pour un utilisateur ou global.
     * GET /api/admin/audit?userId=...&page=1&limit=20&action=...&search=...
     */
    #[Route('/audit', name: 'api_admin_audit', methods: ['GET'])]
    public function getAuditLogs(Request $request, AuditLogRepository $auditLogRepository): JsonResponse
    {
        $userId = $request->query->getInt('userId');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $action = $request->query->get('action');
        $search = $request->query->get('search', $request->query->get('q', ''));

        // If userId is provided, get logs for that specific user
        if ($userId) {
            $user = $this->userRepository->find($userId);

            if (!$user) {
                return new JsonResponse([
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'Utilisateur non trouvé',
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            $result = $auditLogRepository->findPaginatedByTargetUser($user, $page, $limit);
        } else {
            // Get all audit logs
            $result = $auditLogRepository->findAllPaginated($page, $limit, $action ?: null, $search ?: null);
        }

        $logsData = array_map(function ($log) {
            return [
                'id' => $log->getId(),
                'action' => $log->getAction(),
                'actorUser' => $log->getActorUser() ? [
                    'id' => $log->getActorUser()->getId(),
                    'email' => $log->getActorUser()->getEmail(),
                    'nomComplet' => $log->getActorUser()->getNomComplet(),
                ] : null,
                'targetUser' => $log->getTargetUser() ? [
                    'id' => $log->getTargetUser()->getId(),
                    'email' => $log->getTargetUser()->getEmail(),
                    'nomComplet' => $log->getTargetUser()->getNomComplet(),
                ] : null,
                'metadata' => $log->getMetadata(),
                'ip' => $log->getIp(),
                'userAgent' => $log->getUserAgent(),
                'createdAt' => $log->getCreatedAt()->format('c'),
            ];
        }, $result['items']);

        return new JsonResponse([
            'logs' => $logsData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    /**
     * Get available audit action types for filtering.
     * GET /api/admin/audit/actions
     */
    #[Route('/audit/actions', name: 'api_admin_audit_actions', methods: ['GET'])]
    public function getAuditActions(AuditLogRepository $auditLogRepository): JsonResponse
    {
        $actions = $auditLogRepository->getDistinctActions();

        $actionLabels = [
            'LOGIN' => 'Connexion',
            'LOGOUT' => 'Déconnexion',
            'LOGIN_FAILED' => 'Échec de connexion',
            'PASSWORD_CHANGE' => 'Changement de mot de passe',
            'PASSWORD_RESET_REQUEST' => 'Demande de réinitialisation',
            'PASSWORD_RESET_COMPLETE' => 'Réinitialisation terminée',
            'DELETE_REQUEST' => 'Demande de suppression',
            'DELETE_REQUEST_APPROVED' => 'Suppression approuvée',
            'DELETE_REQUEST_REJECTED' => 'Suppression refusée',
            'SOFT_DELETE' => 'Suppression (soft)',
            'RESTORE' => 'Restauration',
            'HARD_DELETE' => 'Suppression définitive',
            'ROLES_UPDATED' => 'Rôles modifiés',
            'SUSPENDED' => 'Suspendu',
            'UNSUSPENDED' => 'Réactivé',
            'IMPERSONATION_START' => 'Impersonation démarrée',
            'IMPERSONATION_STOP' => 'Impersonation terminée',
            'EMAIL_VERIFIED' => 'Email vérifié',
            'EMAIL_VERIFICATION_SENT' => 'Vérification email envoyée',
            'DATA_EXPORTED' => 'Données exportées',
            'PROFILE_UPDATED' => 'Profil mis à jour',
        ];

        $result = array_map(function ($action) use ($actionLabels) {
            return [
                'code' => $action,
                'label' => $actionLabels[$action] ?? $action,
            ];
        }, $actions);

        return new JsonResponse(['actions' => $result]);
    }

    /**
     * Feature 2: Liste des demandes (suppression compte, etc.).
     * GET /api/admin/requests?status=PENDING&type=DELETE_ACCOUNT&page=1&limit=20
     */
    #[Route('/requests', name: 'api_admin_requests', methods: ['GET'])]
    public function getRequests(Request $request, AccountActionRequestRepository $requestRepository): JsonResponse
    {
        $status = $request->query->get('status');
        $type = $request->query->get('type');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $result = $requestRepository->findPaginatedByStatusAndType($status, $type, $page, $limit);

        $requestsData = array_map(function (AccountActionRequest $req) {
            return [
                'id' => $req->getId(),
                'type' => $req->getType(),
                'status' => $req->getStatus(),
                'user' => [
                    'id' => $req->getUser()->getId(),
                    'email' => $req->getUser()->getEmail(),
                    'nomComplet' => $req->getUser()->getNomComplet(),
                ],
                'message' => $req->getMessage(),
                'createdAt' => $req->getCreatedAt()->format('c'),
                'handledAt' => $req->getHandledAt()?->format('c'),
                'handledBy' => $req->getHandledBy() ? [
                    'id' => $req->getHandledBy()->getId(),
                    'email' => $req->getHandledBy()->getEmail(),
                ] : null,
            ];
        }, $result['items']);

        return new JsonResponse([
            'requests' => $requestsData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    /**
     * Feature 2: Traiter une demande (approve/reject).
     * PATCH /api/admin/requests/{id}
     */
    #[Route('/requests/{id}', name: 'api_admin_handle_request', methods: ['PATCH'])]
    public function handleRequest(int $id, Request $request, AccountActionRequestRepository $requestRepository): JsonResponse
    {
        $actionRequest = $requestRepository->find($id);

        if (!$actionRequest) {
            return new JsonResponse([
                'error' => [
                    'code' => 'REQUEST_NOT_FOUND',
                    'message' => 'Demande non trouvée',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$actionRequest->isPending()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'REQUEST_ALREADY_HANDLED',
                    'message' => 'Cette demande a déjà été traitée',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;
        $message = $data['message'] ?? null;

        if (!in_array($status, [AccountActionRequest::STATUS_APPROVED, AccountActionRequest::STATUS_REJECTED])) {
            return new JsonResponse([
                'error' => [
                    'code' => 'INVALID_STATUS',
                    'message' => 'Le statut doit être APPROVED ou REJECTED',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $admin */
        $admin = $this->getUser();
        $targetUser = $actionRequest->getUser();

        if ($status === AccountActionRequest::STATUS_APPROVED) {
            $actionRequest->approve($admin);

            // Handle the actual action based on type
            if ($actionRequest->getType() === AccountActionRequest::TYPE_DELETE_ACCOUNT) {
                $targetUser->softDelete();
                $this->revokeUserTokens($targetUser);
                $this->auditService->logDeleteRequestApproved($admin, $targetUser);
                $this->notificationService->notifyAccountDeleteApproved($targetUser);
            }
        } else {
            $actionRequest->reject($admin, $message);
            $this->auditService->logDeleteRequestRejected($admin, $targetUser);
            $this->notificationService->notifyAccountDeleteRejected($targetUser, $message);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Demande traitée avec succès',
            'request' => [
                'id' => $actionRequest->getId(),
                'status' => $actionRequest->getStatus(),
                'handledAt' => $actionRequest->getHandledAt()->format('c'),
            ],
        ]);
    }

    /**
     * Obtenir les rôles disponibles.
     * GET /api/admin/roles
     */
    #[Route('/roles', name: 'api_admin_roles', methods: ['GET'])]
    public function getAvailableRoles(): JsonResponse
    {
        $roles = [
            ['code' => 'ROLE_USER', 'label' => 'Utilisateur', 'description' => 'Accès basique à l\'application'],
            ['code' => 'ROLE_SEMI_ADMIN', 'label' => 'Semi-Admin', 'description' => 'Voir les stats mais pas gérer les users'],
            ['code' => 'ROLE_MODERATOR', 'label' => 'Modérateur', 'description' => 'Modérer le contenu utilisateur'],
            ['code' => 'ROLE_ANALYST', 'label' => 'Analyste', 'description' => 'Accès aux rapports et analytics'],
            ['code' => 'ROLE_ADMIN', 'label' => 'Administrateur', 'description' => 'Accès complet au système'],
        ];

        // Only show SUPER_ADMIN to super admins
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            $roles[] = ['code' => 'ROLE_SUPER_ADMIN', 'label' => 'Super Admin', 'description' => 'Contrôle total + impersonation'];
        }

        return new JsonResponse(['roles' => $roles]);
    }

    /**
     * Serialize a user for admin view.
     */
    private function serializeUserForAdmin(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nomComplet' => $user->getNomComplet(),
            'roles' => $user->getRoles(),
            'dateInscription' => $user->getDateInscription()?->format('c'),
            'dateDerniereConnexion' => $user->getDateDerniereConnexion()?->format('c'),
            'isEmailVerified' => $user->isEmailVerified(),
            'emailVerifiedAt' => $user->getEmailVerifiedAt()?->format('c'),
            'isSuspended' => $user->isSuspended(),
            'suspendedUntil' => $user->getSuspendedUntil()?->format('c'),
            'suspensionReason' => $user->getSuspensionReason(),
            'isDeleted' => $user->isDeleted(),
            'deletedAt' => $user->getDeletedAt()?->format('c'),
        ];
    }

    /**
     * Revoke all refresh tokens for a user.
     */
    private function revokeUserTokens(User $user): void
    {
        // Using Gesdinet's refresh token manager
        $refreshTokens = $this->entityManager
            ->getRepository('Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken')
            ->findBy(['username' => $user->getEmail()]);

        foreach ($refreshTokens as $token) {
            $this->entityManager->remove($token);
        }

        $this->entityManager->flush();
    }
}
