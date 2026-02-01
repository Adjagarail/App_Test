<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\AuditService;
use App\Service\SessionService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

class JwtAuthenticationSubscriber implements EventSubscriberInterface
{
    // Store the generated JTI to use when creating session
    private ?string $currentJti = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditService $auditService,
        private readonly SessionService $sessionService,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_authentication_success' => 'onAuthenticationSuccess',
            'lexik_jwt_authentication.on_authentication_failure' => 'onAuthenticationFailure',
            'lexik_jwt_authentication.on_jwt_created' => 'onJwtCreated',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Check if user can login (not deleted, not suspended)
        if (!$user->canLogin()) {
            // This will be handled by a custom authenticator
            return;
        }

        // Update last login date
        $user->setDateDerniereConnexion(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Audit log
        $this->auditService->logLogin($user);

        // Create session if we have a JTI
        if ($this->currentJti !== null) {
            $request = $this->requestStack->getCurrentRequest();
            $ip = $request?->getClientIp();
            $userAgent = $request?->headers->get('User-Agent');

            $this->sessionService->createSession($user, $ip, $userAgent, $this->currentJti);

            // Reset for next authentication
            $this->currentJti = null;
        }
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();
        $request = $event->getRequest();

        if ($request !== null) {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? $data['username'] ?? 'unknown';

            $this->auditService->logLoginFailed($email);
        }
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Generate unique JWT ID for session tracking
        $this->currentJti = Uuid::v4()->toRfc4122();

        // Add custom claims to JWT
        $payload = $event->getData();
        $payload['id'] = $user->getId();
        $payload['nomComplet'] = $user->getNomComplet();
        $payload['isEmailVerified'] = $user->isEmailVerified();
        $payload['jti'] = $this->currentJti;

        $event->setData($payload);
    }
}
