<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class TokenRefreshController extends AbstractController
{
    #[Route('/api/token/refresh', name: 'api_refresh_token', methods: ['POST'])]
    public function refresh(
        Request $request,
        RefreshTokenManagerInterface $refreshTokenManager,
        ExtractorInterface $extractor,
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            // Extraire le refresh token de la requête
            $refreshTokenString = $extractor->getRefreshToken($request, 'refresh_token');

            if (!$refreshTokenString) {
                return new JsonResponse([
                    'code' => 401,
                    'message' => 'Refresh token manquant'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Récupérer le refresh token depuis la base de données
            $refreshToken = $refreshTokenManager->get($refreshTokenString);

            if (!$refreshToken || !$refreshToken->isValid()) {
                return new JsonResponse([
                    'code' => 401,
                    'message' => 'Refresh token invalide ou expiré'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Récupérer l'utilisateur associé au refresh token
            $username = $refreshToken->getUsername();
            if (!$username) {
                return new JsonResponse([
                    'code' => 401,
                    'message' => 'Utilisateur non trouvé dans le refresh token'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Récupérer l'objet User complet depuis la base de données
            $user = $em->getRepository(User::class)->findOneBy(['email' => $username]);
            if (!$user) {
                return new JsonResponse([
                    'code' => 401,
                    'message' => 'Utilisateur non trouvé'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Générer un nouveau JWT token
            $newToken = $jwtManager->create($user);

            return new JsonResponse([
                'token' => $newToken,
                'refresh_token' => $refreshTokenString
            ]);

        } catch (AuthenticationException $e) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Échec du rafraîchissement du token'
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'code' => 500,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
