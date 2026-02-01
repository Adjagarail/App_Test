<?php

namespace App\Controller;

use App\Entity\AccountActionRequest;
use App\Entity\User;
use App\Repository\AccountActionRequestRepository;
use App\Repository\AuditLogRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly AuditService $auditService,
        private readonly NotificationService $notificationService,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Feature 3: Modifier le profil (sauf email).
     * PATCH /api/me
     */
    #[Route('', name: 'api_me_update', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        // Email is read-only
        if (isset($data['email'])) {
            return new JsonResponse([
                'error' => [
                    'code' => 'EMAIL_READ_ONLY',
                    'message' => 'L\'email ne peut pas être modifié via cette méthode',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $changedFields = [];

        // Update allowed fields
        if (isset($data['nomComplet'])) {
            $oldValue = $user->getNomComplet();
            $user->setNomComplet($data['nomComplet']);
            if ($oldValue !== $data['nomComplet']) {
                $changedFields[] = 'nomComplet';
            }
        }

        if (empty($changedFields)) {
            return new JsonResponse([
                'message' => 'Aucune modification effectuée',
                'user' => $this->serializeUser($user),
            ]);
        }

        $this->entityManager->flush();

        // Audit
        $this->auditService->logProfileUpdated($user, $changedFields);

        return new JsonResponse([
            'message' => 'Profil mis à jour avec succès',
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Feature 3: Changer le mot de passe => déconnexion forcée.
     * PATCH /api/me/password
     */
    #[Route('/password', name: 'api_me_password', methods: ['PATCH'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        $confirmPassword = $data['confirmNewPassword'] ?? $data['confirmPassword'] ?? '';

        // Validation
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'MISSING_FIELDS',
                    'message' => 'Tous les champs sont requis',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($newPassword !== $confirmPassword) {
            return new JsonResponse([
                'error' => [
                    'code' => 'PASSWORD_MISMATCH',
                    'message' => 'Les nouveaux mots de passe ne correspondent pas',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($newPassword) < 6) {
            return new JsonResponse([
                'error' => [
                    'code' => 'PASSWORD_TOO_SHORT',
                    'message' => 'Le mot de passe doit contenir au moins 6 caractères',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'INVALID_CURRENT_PASSWORD',
                    'message' => 'Le mot de passe actuel est incorrect',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Hash and set new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();

        // Revoke all refresh tokens to force re-login
        $this->revokeUserTokens($user);

        // Audit
        $this->auditService->logPasswordChange($user);

        // Notify
        $this->notificationService->notifyPasswordChanged($user);

        return new JsonResponse([
            'message' => 'Mot de passe modifié avec succès. Veuillez vous reconnecter.',
            'requiresRelogin' => true,
        ]);
    }

    /**
     * Feature 2: Demander la suppression de son compte.
     * POST /api/me/requests/delete-account
     */
    #[Route('/requests/delete-account', name: 'api_me_delete_request', methods: ['POST'])]
    public function requestDeleteAccount(
        Request $request,
        AccountActionRequestRepository $requestRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        // Check for existing pending request
        $existingRequest = $requestRepository->findPendingByUserAndType(
            $user,
            AccountActionRequest::TYPE_DELETE_ACCOUNT
        );

        if ($existingRequest) {
            return new JsonResponse([
                'error' => [
                    'code' => 'REQUEST_ALREADY_EXISTS',
                    'message' => 'Une demande de suppression est déjà en cours',
                    'details' => [
                        'requestId' => $existingRequest->getId(),
                        'createdAt' => $existingRequest->getCreatedAt()->format('c'),
                    ],
                ],
            ], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        $deleteRequest = new AccountActionRequest();
        $deleteRequest->setUser($user);
        $deleteRequest->setType(AccountActionRequest::TYPE_DELETE_ACCOUNT);
        $deleteRequest->setMessage($data['reason'] ?? null);

        $this->entityManager->persist($deleteRequest);
        $this->entityManager->flush();

        // Audit
        $this->auditService->logDeleteRequest($user);

        // Notify all admins and super admins
        $admins = $this->userRepository->findActiveByRole('ROLE_ADMIN');
        $superAdmins = $this->userRepository->findActiveByRole('ROLE_SUPER_ADMIN');

        $notifiedIds = [];
        foreach (array_merge($admins, $superAdmins) as $admin) {
            // Avoid duplicate notifications if user has both roles
            if (!in_array($admin->getId(), $notifiedIds)) {
                $this->notificationService->notifyAccountDeleteRequested($admin, $user);
                $notifiedIds[] = $admin->getId();
            }
        }

        return new JsonResponse([
            'message' => 'Demande de suppression envoyée',
            'request' => [
                'id' => $deleteRequest->getId(),
                'status' => $deleteRequest->getStatus(),
                'createdAt' => $deleteRequest->getCreatedAt()->format('c'),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Feature 8: Liste des notifications paginées.
     * GET /api/me/notifications?page=1&limit=20&unread=true|false
     */
    #[Route('/notifications', name: 'api_me_notifications', methods: ['GET'])]
    public function getNotifications(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $unreadOnly = null;

        if ($request->query->has('unread')) {
            $unreadOnly = $request->query->getBoolean('unread');
        }

        $result = $notificationRepository->findPaginatedByRecipient($user, $page, $limit, $unreadOnly);
        $unreadCount = $notificationRepository->countUnreadByRecipient($user);

        $notificationsData = array_map(function ($notification) {
            return [
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'payload' => $notification->getPayload(),
                'isRead' => $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()->format('c'),
                'readAt' => $notification->getReadAt()?->format('c'),
            ];
        }, $result['items']);

        return new JsonResponse([
            'notifications' => $notificationsData,
            'unreadCount' => $unreadCount,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    /**
     * Feature 8: Marquer une notification comme lue.
     * PATCH /api/me/notifications/{id}
     */
    #[Route('/notifications/{id}', name: 'api_me_notification_read', methods: ['PATCH'])]
    public function markNotificationAsRead(int $id, NotificationRepository $notificationRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notification = $notificationRepository->find($id);

        if (!$notification) {
            return new JsonResponse([
                'error' => [
                    'code' => 'NOTIFICATION_NOT_FOUND',
                    'message' => 'Notification non trouvée',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        // Security check: user can only read their own notifications
        if ($notification->getRecipient()->getId() !== $user->getId()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Vous n\'avez pas accès à cette notification',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $notification->markAsRead();
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Notification marquée comme lue',
            'notification' => [
                'id' => $notification->getId(),
                'isRead' => $notification->isRead(),
                'readAt' => $notification->getReadAt()->format('c'),
            ],
        ]);
    }

    /**
     * Feature 8: Marquer toutes les notifications comme lues.
     * PATCH /api/me/notifications/mark-all-read
     */
    #[Route('/notifications/mark-all-read', name: 'api_me_notifications_mark_all_read', methods: ['PATCH'])]
    public function markAllNotificationsAsRead(NotificationRepository $notificationRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $count = $notificationRepository->markAllAsReadByRecipient($user);

        return new JsonResponse([
            'message' => 'Toutes les notifications ont été marquées comme lues',
            'count' => $count,
        ]);
    }

    /**
     * Feature 10: Export des données utilisateur.
     * GET /api/me/export
     */
    #[Route('/export', name: 'api_me_export', methods: ['GET'])]
    public function exportData(
        NotificationRepository $notificationRepository,
        AuditLogRepository $auditLogRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        // Rate limiting check (simple version - could use a dedicated service)
        $lastExport = $auditLogRepository->findOneBy([
            'actorUser' => $user,
            'action' => 'DATA_EXPORTED',
        ], ['createdAt' => 'DESC']);

        if ($lastExport) {
            $oneHourAgo = new \DateTimeImmutable('-1 hour');
            if ($lastExport->getCreatedAt() > $oneHourAgo) {
                $nextAllowed = $lastExport->getCreatedAt()->modify('+1 hour');
                return new JsonResponse([
                    'error' => [
                        'code' => 'RATE_LIMITED',
                        'message' => 'Vous ne pouvez exporter vos données qu\'une fois par heure',
                        'details' => [
                            'nextAllowedAt' => $nextAllowed->format('c'),
                        ],
                    ],
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }
        }

        // Collect user data
        $userData = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nomComplet' => $user->getNomComplet(),
            'roles' => $user->getRoles(),
            'dateInscription' => $user->getDateInscription()?->format('c'),
            'dateDerniereConnexion' => $user->getDateDerniereConnexion()?->format('c'),
            'isEmailVerified' => $user->isEmailVerified(),
            'emailVerifiedAt' => $user->getEmailVerifiedAt()?->format('c'),
        ];

        // Get notifications (last 100)
        $notifications = $notificationRepository->findByRecipient($user, 100);
        $notificationsData = array_map(function ($notification) {
            return [
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'payload' => $notification->getPayload(),
                'isRead' => $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()->format('c'),
            ];
        }, $notifications);

        // Get audit logs (last 100)
        $auditLogs = $auditLogRepository->findByActorUser($user, 100);
        $auditData = array_map(function ($log) {
            return [
                'action' => $log->getAction(),
                'metadata' => $log->getMetadata(),
                'createdAt' => $log->getCreatedAt()->format('c'),
            ];
        }, $auditLogs);

        // Log the export
        $this->auditService->logDataExported($user);

        return new JsonResponse([
            'exportedAt' => (new \DateTimeImmutable())->format('c'),
            'user' => $userData,
            'notifications' => $notificationsData,
            'activityLog' => $auditData,
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nomComplet' => $user->getNomComplet(),
            'roles' => $user->getRoles(),
            'dateInscription' => $user->getDateInscription()?->format('c'),
            'dateDerniereConnexion' => $user->getDateDerniereConnexion()?->format('c'),
            'isEmailVerified' => $user->isEmailVerified(),
        ];
    }

    private function revokeUserTokens(User $user): void
    {
        $refreshTokens = $this->entityManager
            ->getRepository('Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken')
            ->findBy(['username' => $user->getEmail()]);

        foreach ($refreshTokens as $token) {
            $this->entityManager->remove($token);
        }

        $this->entityManager->flush();
    }
}
