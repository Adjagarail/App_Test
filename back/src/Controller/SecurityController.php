<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
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
     * Déconnexion (côté client uniquement avec JWT stateless)
     * Le client doit simplement supprimer le token de son stockage local
     */
    #[Route('/logout', name: 'app_logout', methods: ['POST', 'GET'])]
    public function logout(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Pour se déconnecter avec JWT, supprimez simplement le token de votre stockage local (localStorage, sessionStorage, etc.)',
            'client_side_example' => [
                'javascript' => [
                    'localStorage.removeItem("jwt_token")',
                    'localStorage.removeItem("refresh_token")',
                    'window.location.href = "/jwt-example.html"'
                ]
            ],
            'note' => 'JWT est stateless, il n\'y a pas de session côté serveur à détruire. Le token reste valide jusqu\'à expiration (15 minutes).'
        ]);
    }
}
