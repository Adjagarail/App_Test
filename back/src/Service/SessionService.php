<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;

class SessionService
{
    // Default: session is "active" if seen within last 5 minutes
    public const DEFAULT_ACTIVE_MINUTES = 5;

    public function __construct(
        private readonly UserSessionRepository $sessionRepository,
        private readonly MercureService $mercureService
    ) {
    }

    /**
     * Create a new session for a user.
     */
    public function createSession(User $user, ?string $ip, ?string $userAgent, string $tokenJti): UserSession
    {
        $session = new UserSession();
        $session->setUser($user);
        $session->setIp($ip);
        $session->setUserAgent($userAgent);
        $session->setTokenJti($tokenJti);

        $this->sessionRepository->save($session, true);

        // Publish active users update
        $this->publishActiveUsersUpdate();

        return $session;
    }

    /**
     * End a session by JWT ID.
     */
    public function endSession(string $tokenJti): bool
    {
        $result = $this->sessionRepository->endSessionByJti($tokenJti);

        if ($result) {
            // Publish active users update
            $this->publishActiveUsersUpdate();
        }

        return $result;
    }

    /**
     * End all sessions for a user.
     */
    public function endAllUserSessions(User $user): int
    {
        $count = $this->sessionRepository->endAllUserSessions($user);

        if ($count > 0) {
            // Publish active users update
            $this->publishActiveUsersUpdate();
        }

        return $count;
    }

    /**
     * Update lastSeenAt for a session.
     *
     * @param int $minIntervalSeconds Rate limit for updates (default 60s)
     */
    public function updateLastSeen(string $tokenJti, int $minIntervalSeconds = 60): bool
    {
        return $this->sessionRepository->updateLastSeen($tokenJti, $minIntervalSeconds);
    }

    /**
     * Get active users metrics.
     *
     * @param int $withinMinutes Consider "active" if seen within this many minutes
     * @return array{activeUsers: int, activeSessions: int, updatedAt: string}
     */
    public function getActiveUsersMetrics(int $withinMinutes = self::DEFAULT_ACTIVE_MINUTES): array
    {
        $metrics = $this->sessionRepository->countActiveSessions($withinMinutes);

        return [
            'activeUsers' => $metrics['activeUsers'],
            'activeSessions' => $metrics['activeSessions'],
            'updatedAt' => (new \DateTimeImmutable())->format('c'),
            'activeDefinition' => "Seen within last {$withinMinutes} minutes",
        ];
    }

    /**
     * Find active session by JWT ID.
     */
    public function findActiveSession(string $tokenJti): ?UserSession
    {
        return $this->sessionRepository->findActiveByJti($tokenJti);
    }

    /**
     * Get all active sessions for a user.
     *
     * @return UserSession[]
     */
    public function getUserActiveSessions(User $user): array
    {
        return $this->sessionRepository->findActiveByUser($user);
    }

    /**
     * Publish active users count via Mercure.
     */
    public function publishActiveUsersUpdate(): void
    {
        $metrics = $this->getActiveUsersMetrics();
        $this->mercureService->publishActiveUsersCount($metrics['activeSessions']);
    }

    /**
     * Cleanup old ended sessions.
     */
    public function cleanupOldSessions(int $olderThanDays = 30): int
    {
        return $this->sessionRepository->cleanupOldSessions($olderThanDays);
    }
}
