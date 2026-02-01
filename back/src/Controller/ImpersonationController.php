<?php

namespace App\Controller;

use App\Entity\ImpersonationActivity;
use App\Entity\ImpersonationSession;
use App\Entity\User;
use App\Repository\ImpersonationSessionRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\ImpersonationActivityService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class ImpersonationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ImpersonationSessionRepository $sessionRepository,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuditService $auditService,
        private readonly ImpersonationActivityService $activityService
    ) {
    }

    /**
     * Feature 12: Start impersonation.
     * POST /api/admin/users/{id}/impersonate
     */
    #[Route('/users/{id}/impersonate', name: 'api_admin_impersonate_start', methods: ['POST'])]
    public function startImpersonation(int $id, Request $request): JsonResponse
    {
        $targetUser = $this->userRepository->find($id);

        if (!$targetUser) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        // Cannot impersonate yourself
        if ($targetUser->getId() === $admin->getId()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_IMPERSONATE_SELF',
                    'message' => 'Vous ne pouvez pas vous auto-impersonner',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Cannot impersonate ADMIN or SUPER_ADMIN
        if ($targetUser->hasRole('ROLE_ADMIN') || $targetUser->hasRole('ROLE_SUPER_ADMIN')) {
            return new JsonResponse([
                'error' => [
                    'code' => 'CANNOT_IMPERSONATE_ADMIN',
                    'message' => 'Impossible d\'impersonner un administrateur',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Cannot impersonate deleted or suspended users
        if (!$targetUser->canLogin()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'USER_NOT_ACTIVE',
                    'message' => 'Impossible d\'impersonner un utilisateur inactif ou suspendu',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Check for existing active session
        $existingSession = $this->sessionRepository->findActiveByImpersonator($admin);
        if ($existingSession) {
            return new JsonResponse([
                'error' => [
                    'code' => 'SESSION_ALREADY_EXISTS',
                    'message' => 'Une session d\'impersonation est déjà active',
                    'details' => [
                        'sessionId' => $existingSession->getId(),
                        'targetUserId' => $existingSession->getTargetUser()->getId(),
                        'expiresAt' => $existingSession->getExpiresAt()->format('c'),
                    ],
                ],
            ], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        // Create impersonation session
        $session = new ImpersonationSession();
        $session->setImpersonator($admin);
        $session->setTargetUser($targetUser);
        $session->setReason($reason);
        $session->setIp($request->getClientIp());
        $session->setUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        // Generate impersonation token
        $impersonationToken = $this->generateImpersonationToken($targetUser, $session);

        // Audit with full details
        $this->auditService->logImpersonationStart(
            $admin,
            $targetUser,
            $session->getId(),
            $reason,
            $session->getExpiresAt()
        );

        return new JsonResponse([
            'message' => 'Impersonation démarrée',
            'token' => $impersonationToken,
            'refreshToken' => '', // Impersonation uses short-lived tokens only
            'sessionId' => (string) $session->getId(),
            'expiresAt' => $session->getExpiresAt()->format('c'),
            'targetUser' => [
                'id' => $targetUser->getId(),
                'email' => $targetUser->getEmail(),
                'nomComplet' => $targetUser->getNomComplet(),
                'roles' => $targetUser->getRoles(),
            ],
        ]);
    }

    /**
     * Feature 12: Stop impersonation.
     * POST /api/admin/impersonation/stop
     */
    #[Route('/impersonation/stop', name: 'api_admin_impersonate_stop', methods: ['POST'])]
    public function stopImpersonation(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        // Find active session
        $session = $this->sessionRepository->findActiveByImpersonator($admin);

        if (!$session) {
            return new JsonResponse([
                'error' => [
                    'code' => 'NO_ACTIVE_SESSION',
                    'message' => 'Aucune session d\'impersonation active',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $targetUser = $session->getTargetUser();

        // Calculate session duration
        $startTime = $session->getCreatedAt();
        $endTime = new \DateTimeImmutable();
        $interval = $startTime->diff($endTime);
        $duration = $interval->format('%i min %s sec');
        if ($interval->h > 0) {
            $duration = $interval->format('%h h %i min');
        }

        // Revoke the session
        $session->revoke();
        $this->entityManager->flush();

        // Audit with session details
        $this->auditService->logImpersonationStop(
            $admin,
            $targetUser,
            $session->getId(),
            $duration
        );

        return new JsonResponse([
            'message' => 'Impersonation terminée',
            'sessionId' => $session->getId(),
            'duration' => $duration,
        ]);
    }

    /**
     * Get current impersonation status.
     * GET /api/admin/impersonation/status
     */
    #[Route('/impersonation/status', name: 'api_admin_impersonate_status', methods: ['GET'])]
    public function getImpersonationStatus(): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $session = $this->sessionRepository->findActiveByImpersonator($admin);

        if (!$session) {
            return new JsonResponse([
                'isImpersonating' => false,
            ]);
        }

        return new JsonResponse([
            'isImpersonating' => true,
            'session' => [
                'id' => $session->getId(),
                'targetUser' => [
                    'id' => $session->getTargetUser()->getId(),
                    'email' => $session->getTargetUser()->getEmail(),
                    'nomComplet' => $session->getTargetUser()->getNomComplet(),
                ],
                'createdAt' => $session->getCreatedAt()->format('c'),
                'expiresAt' => $session->getExpiresAt()->format('c'),
            ],
        ]);
    }

    /**
     * Get impersonation session history with activities.
     * GET /api/admin/impersonation/sessions
     */
    #[Route('/impersonation/sessions', name: 'api_admin_impersonate_sessions', methods: ['GET'])]
    public function getImpersonationSessions(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));

        $result = $this->sessionRepository->findAllPaginated($page, $limit);

        $sessionsData = array_map(function (ImpersonationSession $session) {
            $summary = $this->activityService->getSessionSummary($session);

            return [
                'id' => $session->getId(),
                'impersonator' => [
                    'id' => $session->getImpersonator()->getId(),
                    'email' => $session->getImpersonator()->getEmail(),
                    'nomComplet' => $session->getImpersonator()->getNomComplet(),
                ],
                'targetUser' => [
                    'id' => $session->getTargetUser()->getId(),
                    'email' => $session->getTargetUser()->getEmail(),
                    'nomComplet' => $session->getTargetUser()->getNomComplet(),
                ],
                'reason' => $session->getReason(),
                'createdAt' => $session->getCreatedAt()->format('c'),
                'expiresAt' => $session->getExpiresAt()->format('c'),
                'revokedAt' => $session->getRevokedAt()?->format('c'),
                'isActive' => $session->isActive(),
                'ip' => $session->getIp(),
                'summary' => $summary,
            ];
        }, $result['items']);

        return new JsonResponse([
            'sessions' => $sessionsData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    /**
     * Get activities for a specific impersonation session.
     * GET /api/admin/impersonation/sessions/{sessionId}/activities
     */
    #[Route('/impersonation/sessions/{sessionId}/activities', name: 'api_admin_impersonate_session_activities', methods: ['GET'])]
    public function getSessionActivities(string $sessionId, Request $request): JsonResponse
    {
        $session = $this->sessionRepository->find($sessionId);

        if (!$session) {
            return new JsonResponse([
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Session d\'impersonation non trouvée',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));

        $result = $this->activityService->getSessionActivitiesPaginated($session, $page, $limit);

        $activitiesData = array_map(function (ImpersonationActivity $activity) {
            return [
                'id' => $activity->getId(),
                'type' => $activity->getType(),
                'action' => $activity->getAction(),
                'path' => $activity->getPath(),
                'method' => $activity->getMethod(),
                'details' => $activity->getDetails(),
                'createdAt' => $activity->getCreatedAt()->format('c'),
            ];
        }, $result['items']);

        return new JsonResponse([
            'session' => [
                'id' => $session->getId(),
                'impersonator' => $session->getImpersonator()->getEmail(),
                'targetUser' => $session->getTargetUser()->getEmail(),
                'createdAt' => $session->getCreatedAt()->format('c'),
            ],
            'activities' => $activitiesData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    /**
     * Generate a special JWT token for impersonation with limited scope.
     */
    private function generateImpersonationToken(User $targetUser, ImpersonationSession $session): string
    {
        // Create a custom token with impersonation claims
        $payload = [
            'username' => $targetUser->getEmail(),
            'roles' => $targetUser->getRoles(),
            'nomComplet' => $targetUser->getNomComplet(),
            'isImpersonating' => true,
            'impersonationSessionId' => $session->getId(),
            'impersonatorId' => $session->getImpersonator()->getId(),
            'exp' => $session->getExpiresAt()->getTimestamp(),
            'jti' => $session->getTokenJti(),
        ];

        return $this->jwtManager->createFromPayload($targetUser, $payload);
    }
}
