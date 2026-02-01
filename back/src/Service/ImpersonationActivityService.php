<?php

namespace App\Service;

use App\Entity\ImpersonationActivity;
use App\Entity\ImpersonationSession;
use App\Repository\ImpersonationActivityRepository;
use App\Repository\ImpersonationSessionRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class ImpersonationActivityService
{
    public function __construct(
        private readonly ImpersonationActivityRepository $activityRepository,
        private readonly ImpersonationSessionRepository $sessionRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Log an activity during impersonation.
     */
    public function logActivity(
        ImpersonationSession $session,
        string $type,
        string $action,
        ?string $path = null,
        ?string $method = null,
        ?array $details = null
    ): ImpersonationActivity {
        $activity = new ImpersonationActivity();
        $activity->setSession($session);
        $activity->setType($type);
        $activity->setAction($action);
        $activity->setPath($path);
        $activity->setMethod($method);
        $activity->setDetails($details);

        $this->activityRepository->save($activity, true);

        return $activity;
    }

    /**
     * Log a page view during impersonation.
     */
    public function logPageView(ImpersonationSession $session, string $path): ImpersonationActivity
    {
        return $this->logActivity(
            $session,
            ImpersonationActivity::TYPE_PAGE_VIEW,
            'Viewed page',
            $path
        );
    }

    /**
     * Log an API call during impersonation.
     */
    public function logApiCall(
        ImpersonationSession $session,
        string $method,
        string $path,
        ?string $action = null,
        ?array $details = null
    ): ImpersonationActivity {
        return $this->logActivity(
            $session,
            ImpersonationActivity::TYPE_API_CALL,
            $action ?? "$method $path",
            $path,
            $method,
            $details
        );
    }

    /**
     * Log a specific action during impersonation.
     */
    public function logAction(
        ImpersonationSession $session,
        string $action,
        ?array $details = null
    ): ImpersonationActivity {
        $request = $this->requestStack->getCurrentRequest();

        return $this->logActivity(
            $session,
            ImpersonationActivity::TYPE_ACTION,
            $action,
            $request?->getPathInfo(),
            $request?->getMethod(),
            $details
        );
    }

    /**
     * Get all activities for a session.
     *
     * @return ImpersonationActivity[]
     */
    public function getSessionActivities(ImpersonationSession $session): array
    {
        return $this->activityRepository->findBySession($session);
    }

    /**
     * Get activities with pagination.
     */
    public function getSessionActivitiesPaginated(
        ImpersonationSession $session,
        int $page = 1,
        int $limit = 50
    ): array {
        return $this->activityRepository->findBySessionPaginated($session, $page, $limit);
    }

    /**
     * Get session summary.
     */
    public function getSessionSummary(ImpersonationSession $session): array
    {
        return $this->activityRepository->getSessionSummary($session);
    }

    /**
     * Find session by ID.
     */
    public function findSession(string $sessionId): ?ImpersonationSession
    {
        return $this->sessionRepository->find($sessionId);
    }
}
