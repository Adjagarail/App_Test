<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }
    /**
     * Page dashboard protégée par JWT
     * Nécessite un token JWT valide dans le header Authorization
     */
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Si vous voulez retourner du JSON (pour une API)
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            return new JsonResponse([
                'message' => 'Bienvenue sur le dashboard',
                'user' => $user->getUserIdentifier(),
                'stats' => [
                    'connected' => true,
                    'last_login' => date('d-m-Y H:i:s')
                ]
            ]);
        }

        // Sinon, retourner une vue HTML
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * API endpoint pour récupérer les données du dashboard
     */
    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function apiDashboard(): JsonResponse
    {
        $user = $this->getUser();

        // Compter le nombre total d'utilisateurs
        $totalUsers = $this->entityManager->getRepository(User::class)->count([]);

        $now = new \DateTime();
        $activeSessions = (int) $this->entityManager
            ->createQuery('SELECT COUNT(r) FROM App\Entity\RefreshToken r WHERE r.valid > :now')
            ->setParameter('now', $now)
            ->getSingleScalarResult();

        return new JsonResponse([
            'message' => 'Bienvenue sur le dashboard',
            'user' => [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'nomComplet' => $user->getNomComplet()
            ],
            'stats' => [
                'totalUsers' => $totalUsers,
                'todayLogins' => $activeSessions // Session actuelle
            ]
        ]);
    }
}
