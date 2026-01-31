<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    /**
     * IMPORTANT: Cette route /login est gérée par le firewall 'login' dans security.yaml
     * Elle utilise json_login et retourne automatiquement un JWT.
     *
     * Pour se connecter, envoyez une requête POST avec :
     * {
     *   "email": "user@example.com",
     *   "password": "password"
     * }
     *
     * La réponse contiendra :
     * {
     *   "token": "eyJ0eXAi...",
     *   "refresh_token": "abc123..."
     * }
     */
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function loginInfo(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Endpoint de connexion JWT',
            'method' => 'POST',
            'url' => '/login',
            'body' => [
                'email' => 'string',
                'password' => 'string'
            ],
            'response' => [
                'token' => 'JWT access token',
                'refresh_token' => 'JWT refresh token'
            ],
            'examples' => [
                'curl' => 'curl -X POST http://localhost:8080/login -H "Content-Type: application/json" -d \'{"email":"user@example.com","password":"password"}\'',
                'javascript' => 'fetch("/login", { method: "POST", headers: {"Content-Type": "application/json"}, body: JSON.stringify({email: "user@example.com", password: "password"}) })',
            ],
            'documentation' => 'Voir JWT-GUIDE.md pour plus de détails',
            'test_page' => 'http://localhost:8080/jwt-example.html'
        ], 200);
    }

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
                    'window.location.href = "/login"'
                ]
            ],
            'note' => 'JWT est stateless, il n\'y a pas de session côté serveur à détruire. Le token reste valide jusqu\'à expiration (15 minutes).'
        ]);
    }
}
