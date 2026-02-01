<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/password')]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PasswordResetTokenRepository $tokenRepository,
        private MailerService $mailerService,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * Demander un reset de mot de passe
     * POST /api/password/forgot
     */
    #[Route('/forgot', name: 'api_password_forgot', methods: ['POST'])]
    public function forgot(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['error' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        // Toujours retourner un succès pour éviter l'énumération d'utilisateurs
        if (!$user) {
            return new JsonResponse([
                'message' => 'Si un compte existe avec cet email, vous recevrez un lien de réinitialisation.'
            ]);
        }

        // Supprimer les anciens tokens pour cet utilisateur
        $this->tokenRepository->deleteTokensForUser($user->getId());

        // Créer un nouveau token
        $resetToken = new PasswordResetToken();
        $resetToken->setUser($user);

        $this->entityManager->persist($resetToken);
        $this->entityManager->flush();

        // Envoyer l'email
        try {
            $this->mailerService->sendPasswordResetEmail($user, $resetToken->getToken());
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas exposer les détails
            // En prod, on loguerait l'erreur
        }

        return new JsonResponse([
            'message' => 'Si un compte existe avec cet email, vous recevrez un lien de réinitialisation.'
        ]);
    }

    /**
     * Vérifier si un token est valide
     * GET /api/password/verify/{token}
     */
    #[Route('/verify/{token}', name: 'api_password_verify', methods: ['GET'])]
    public function verify(string $token): JsonResponse
    {
        $resetToken = $this->tokenRepository->findValidToken($token);

        if (!$resetToken) {
            return new JsonResponse([
                'valid' => false,
                'error' => 'Token invalide ou expiré'
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'valid' => true,
            'email' => $resetToken->getUser()->getEmail()
        ]);
    }

    /**
     * Réinitialiser le mot de passe
     * POST /api/password/reset
     */
    #[Route('/reset', name: 'api_password_reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $password = $data['password'] ?? null;

        if (!$token || !$password) {
            return new JsonResponse([
                'error' => 'Token et mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 6) {
            return new JsonResponse([
                'error' => 'Le mot de passe doit contenir au moins 6 caractères'
            ], Response::HTTP_BAD_REQUEST);
        }

        $resetToken = $this->tokenRepository->findValidToken($token);

        if (!$resetToken) {
            return new JsonResponse([
                'error' => 'Token invalide ou expiré'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $resetToken->getUser();

        // Mettre à jour le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Supprimer le token utilisé
        $this->entityManager->remove($resetToken);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Mot de passe réinitialisé avec succès'
        ]);
    }
}
