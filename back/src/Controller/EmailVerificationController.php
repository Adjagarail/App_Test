<?php

namespace App\Controller;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\MailerService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EmailVerificationTokenRepository $tokenRepository,
        private readonly MailerService $mailerService,
        private readonly AuditService $auditService,
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Feature 7: Verify email with token.
     * GET /api/auth/verify-email?token=...
     */
    #[Route('/verify-email', name: 'api_auth_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $tokenString = $request->query->get('token');

        if (empty($tokenString)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'MISSING_TOKEN',
                    'message' => 'Le token de vérification est requis',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $token = $this->tokenRepository->findValidToken($tokenString);

        if (!$token) {
            return new JsonResponse([
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Le token est invalide ou a expiré',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $token->getUser();

        if ($user->isEmailVerified()) {
            // Delete the token anyway
            $this->tokenRepository->deleteTokensForUser($user);

            return new JsonResponse([
                'message' => 'Votre email est déjà vérifié',
                'alreadyVerified' => true,
            ]);
        }

        // Verify the email
        $user->verifyEmail();

        // Delete all verification tokens for this user
        $this->tokenRepository->deleteTokensForUser($user);

        $this->entityManager->flush();

        // Audit
        $this->auditService->logEmailVerified($user);

        // Notify
        $this->notificationService->notifyEmailVerified($user);

        return new JsonResponse([
            'message' => 'Votre email a été vérifié avec succès',
            'verified' => true,
        ]);
    }

    /**
     * Feature 7: Resend verification email.
     * POST /api/auth/resend-verification
     */
    #[Route('/resend-verification', name: 'api_auth_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        // If no email provided and user is authenticated, use their email
        $user = null;
        if (empty($email) && $this->getUser() instanceof User) {
            /** @var User $user */
            $user = $this->getUser();
            $email = $user->getEmail();
        } elseif (!empty($email)) {
            $user = $this->userRepository->findByEmail($email);
        }

        // Always return success to prevent user enumeration
        $successResponse = new JsonResponse([
            'message' => 'Si l\'adresse email existe et n\'est pas vérifiée, un email de vérification a été envoyé',
        ]);

        if (!$user) {
            return $successResponse;
        }

        if ($user->isEmailVerified()) {
            return $successResponse;
        }

        // Rate limiting: check if an email was sent recently
        if ($this->tokenRepository->wasRecentlySent($user, 5)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Veuillez attendre quelques minutes avant de demander un nouvel email',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Delete old tokens
        $this->tokenRepository->deleteTokensForUser($user);

        // Create new token
        $token = new EmailVerificationToken();
        $token->setUser($user);
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        // Send email
        $this->mailerService->sendEmailVerification($user, $token->getToken());

        // Audit
        $this->auditService->logEmailVerificationSent($user);

        return $successResponse;
    }
}
