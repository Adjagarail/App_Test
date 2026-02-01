<?php

namespace App\Controller;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly MailerService $mailerService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse([
                'error' => [
                    'code' => 'MISSING_FIELDS',
                    'message' => 'Email et password requis',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'error' => [
                    'code' => 'INVALID_EMAIL',
                    'message' => 'Format d\'email invalide',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate password length
        if (strlen($data['password']) < 6) {
            return new JsonResponse([
                'error' => [
                    'code' => 'PASSWORD_TOO_SHORT',
                    'message' => 'Le mot de passe doit contenir au moins 6 caractères',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse([
                'error' => [
                    'code' => 'EMAIL_EXISTS',
                    'message' => 'Cet email est déjà utilisé',
                ],
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $data['password'])
        );
        $user->setDateInscription(new \DateTimeImmutable());

        // Sauvegarder le nom complet s'il est fourni
        if (isset($data['nomComplet']) && !empty($data['nomComplet'])) {
            $user->setNomComplet($data['nomComplet']);
        }

        // Sauvegarder le nom complet s'il est fourni
        if (isset($data['nomComplet']) && !empty($data['nomComplet'])) {
            $user->setNomComplet($data['nomComplet']);
        }

        $em->persist($user);

        // Create email verification token
        $verificationToken = new EmailVerificationToken();
        $verificationToken->setUser($user);
        $em->persist($verificationToken);

        $em->flush();

        // Send verification email (don't fail registration if email fails)
        try {
            $this->mailerService->sendEmailVerification($user, $verificationToken->getToken());
            $this->logger->info('Verification email sent successfully to: ' . $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('Failed to send verification email: ' . $e->getMessage(), [
                'email' => $user->getEmail(),
                'exception' => $e,
            ]);
        }

        return new JsonResponse([
            'message' => 'Utilisateur créé avec succès. Un email de vérification a été envoyé.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'nomComplet' => $user->getNomComplet(),
                'isEmailVerified' => $user->isEmailVerified(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Endpoint pour créer rapidement un utilisateur de test
     */
    #[Route('/register/test', name: 'app_register_test', methods: ['GET', 'POST'])]
    public function registerTest(
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $email = 'test@example.com';
        $password = 'password';

        // Supprimer l'utilisateur s'il existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setNomComplet('Test User');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );
        $user->setDateInscription(new \DateTimeImmutable());
        // Mark as verified for test user
        $user->verifyEmail();

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Utilisateur de test créé',
            'credentials' => [
                'email' => $email,
                'password' => $password,
            ],
            'test_login' => [
                'curl' => sprintf(
                    'curl -X POST http://localhost:8080/api/login_check -H "Content-Type: application/json" -d \'{"email":"%s","password":"%s"}\'',
                    $email,
                    $password
                ),
                'url' => 'http://localhost:8080/jwt-example.html',
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Endpoint pour créer un admin de test
     */
    #[Route('/register/admin-test', name: 'app_register_admin_test', methods: ['GET', 'POST'])]
    public function registerAdminTest(
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $email = 'admin@example.com';
        $password = 'admin123';

        // Supprimer l'utilisateur s'il existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setNomComplet('Admin Test');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );
        $user->setDateInscription(new \DateTimeImmutable());
        $user->verifyEmail();

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Administrateur de test créé',
            'credentials' => [
                'email' => $email,
                'password' => $password,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Endpoint pour créer un super admin de test
     */
    #[Route('/register/super-admin-test', name: 'app_register_super_admin_test', methods: ['GET', 'POST'])]
    public function registerSuperAdminTest(
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $email = 'superadmin@example.com';
        $password = 'superadmin123';

        // Supprimer l'utilisateur s'il existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setNomComplet('Super Admin Test');
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setPassword(
            $passwordHasher->hashPassword($user, $password)
        );
        $user->setDateInscription(new \DateTimeImmutable());
        $user->verifyEmail();

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Super Administrateur de test créé',
            'credentials' => [
                'email' => $email,
                'password' => $password,
            ],
        ], Response::HTTP_CREATED);
    }
}
