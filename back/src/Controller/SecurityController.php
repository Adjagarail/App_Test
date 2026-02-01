<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditService;
use App\Service\SessionService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly AuditService $auditService,
        private readonly JWTTokenManagerInterface $jwtManager
    ) {
    }

    /**
     * IMPORTANT: La route POST /login est gérée automatiquement par le firewall 'login' dans security.yaml
     * Le firewall utilise json_login et retourne automatiquement un JWT.
     *
     * Pour se connecter :
     * POST /login
     * Content-Type: application/json
     * Body: {"email":"user@example.com","password":"password"}
     *
     * Réponse :
     * {"token":"eyJ0eXAi...","refresh_token":"abc123..."}
     */

    /**
     * Logout - termine la session serveur et invalide le token côté serveur.
     *
     * Le client doit également supprimer le token de son stockage local.
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get JTI from current token
        $jti = $this->getJtiFromRequest($request);
        $sessionEnded = false;

        if ($jti) {
            $sessionEnded = $this->sessionService->endSession($jti);
        }

        // Audit log
        $this->auditService->logLogout($user);

        return new JsonResponse([
            'message' => 'Déconnexion réussie',
            'sessionEnded' => $sessionEnded,
            'note' => 'N\'oubliez pas de supprimer le token de votre stockage local.',
        ]);
    }

    /**
     * Legacy logout endpoint (for backward compatibility).
     */
    #[Route('/logout', name: 'app_logout', methods: ['POST', 'GET'])]
    public function logoutLegacy(Request $request): JsonResponse
    {
        // Try to end session if authenticated
        $user = $this->getUser();
        $sessionEnded = false;

        if ($user instanceof User) {
            $jti = $this->getJtiFromRequest($request);

            if ($jti) {
                $sessionEnded = $this->sessionService->endSession($jti);
            }

            $this->auditService->logLogout($user);
        }

        return new JsonResponse([
            'message' => 'Déconnexion réussie',
            'sessionEnded' => $sessionEnded,
            'note' => 'Supprimez le token de votre stockage local (localStorage, sessionStorage, etc.)',
        ]);
    }

    /**
     * Extract JTI from the Authorization header.
     */
    private function getJtiFromRequest(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        try {
            // Decode JWT payload (base64)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            return $payload['jti'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
