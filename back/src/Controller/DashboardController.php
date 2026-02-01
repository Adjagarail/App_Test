<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
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
    #[IsGranted('ROLE_ADMIN')]
    public function apiDashboard(): JsonResponse
    {
        $user = $this->getUser();
        $userRepo = $this->entityManager->getRepository(User::class);

        // Compter le nombre total d'utilisateurs actifs (non supprimés)
        $totalUsers = (int) $this->entityManager
            ->createQuery('SELECT COUNT(u) FROM App\Entity\User u WHERE u.deletedAt IS NULL')
            ->getSingleScalarResult();

        // Compter les sessions actives (refresh tokens valides)
        $now = new \DateTime();
        $activeSessions = (int) $this->entityManager
            ->createQuery('SELECT COUNT(r) FROM App\Entity\RefreshToken r WHERE r.valid > :now')
            ->setParameter('now', $now)
            ->getSingleScalarResult();

        // Compter les demandes de suppression en attente
        $pendingDeleteRequests = 0;
        try {
            $pendingDeleteRequests = (int) $this->entityManager
                ->createQuery('SELECT COUNT(a) FROM App\Entity\AccountActionRequest a WHERE a.status = :status AND a.type = :type')
                ->setParameter('status', 'PENDING')
                ->setParameter('type', 'DELETE_ACCOUNT')
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            // Entity might not exist yet
        }

        // Compter les utilisateurs suspendus
        $suspendedUsers = (int) $this->entityManager
            ->createQuery('SELECT COUNT(u) FROM App\Entity\User u WHERE u.isSuspended = true AND u.deletedAt IS NULL')
            ->getSingleScalarResult();

        // Compter les emails non vérifiés
        $unverifiedEmails = (int) $this->entityManager
            ->createQuery('SELECT COUNT(u) FROM App\Entity\User u WHERE u.isEmailVerified = false AND u.deletedAt IS NULL')
            ->getSingleScalarResult();

        $totalUsers = $this->userRepository->count([]);

        $now = new \DateTime();

        $activeSessions = (int) $this->entityManager
            ->createQuery('SELECT COUNT(r) FROM App\Entity\RefreshToken r WHERE r.valid > :now')
            ->setParameter('now', $now)
            ->getSingleScalarResult();

        return new JsonResponse([
            'message' => 'Bienvenue sur le dashboard administrateur',
            'user' => [
                'email' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
                'nomComplet' => $user->getNomComplet()
            ],
            'stats' => [
                'totalUsers' => $totalUsers,
                'todayLogins' => $activeSessions,
                'activeSessions' => $activeSessions,
                'pendingDeleteRequests' => $pendingDeleteRequests,
                'suspendedUsers' => $suspendedUsers,
                'unverifiedEmails' => $unverifiedEmails,
                'todayLogins' => $activeSessions
            ]
        ]);
    }
}
