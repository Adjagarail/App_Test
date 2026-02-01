<?php

namespace App\Controller;

use App\Entity\ImpersonationSession;
use App\Entity\User;
use App\Repository\ImpersonationSessionRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
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
        private readonly AuditService $auditService
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

        // Audit
        $this->auditService->logImpersonationStart($admin, $targetUser, $session->getId());

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

        // Revoke the session
        $session->revoke();
        $this->entityManager->flush();

        // Audit
        $this->auditService->logImpersonationStop($admin, $targetUser, $session->getId());

        return new JsonResponse([
            'message' => 'Impersonation terminée',
            'sessionId' => $session->getId(),
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
