<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    /**
     * Page de profil protégée par JWT
     * Nécessite un token JWT valide dans le header Authorization
     */
    #[Route('/profile', name: 'app_profile')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Si vous voulez retourner du JSON (pour une API)
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            return new JsonResponse([
                'message' => 'Profil utilisateur',
                'user' => [
                    'email' => $user->getUserIdentifier(),
                    'roles' => $user->getRoles(),
                ]
            ]);
        }

        // Sinon, retourner une vue HTML
        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * API endpoint pour récupérer les données du profil
     */
    #[Route('/api/profile', name: 'api_profile', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function apiProfile(): JsonResponse
    {
        $user = $this->getUser();

        return new JsonResponse([
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}
