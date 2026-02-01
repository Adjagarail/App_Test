<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    public function log(
        string $action,
        ?User $actor = null,
        ?User $target = null,
        ?array $metadata = null
    ): AuditLog {
        $request = $this->requestStack->getCurrentRequest();

        $log = new AuditLog();
        $log->setAction($action);
        $log->setActorUser($actor);
        $log->setTargetUser($target);
        $log->setMetadata($metadata);

        if ($request !== null) {
            $log->setIp($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->auditLogRepository->save($log, true);

        return $log;
    }

    public function logLogin(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_LOGIN, $user, $user);
    }

    public function logLoginFailed(string $email): AuditLog
    {
        return $this->log(AuditLog::ACTION_LOGIN_FAILED, null, null, [
            'email' => $email,
        ]);
    }

    public function logLogout(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_LOGOUT, $user, $user);
    }

    public function logPasswordChange(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_PASSWORD_CHANGE, $user, $user);
    }

    public function logPasswordResetRequest(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_PASSWORD_RESET_REQUEST, null, $user);
    }

    public function logPasswordResetComplete(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_PASSWORD_RESET_COMPLETE, null, $user);
    }

    public function logDeleteRequest(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_DELETE_REQUEST, $user, $user);
    }

    public function logDeleteRequestApproved(User $admin, User $target): AuditLog
    {
        return $this->log(AuditLog::ACTION_DELETE_REQUEST_APPROVED, $admin, $target);
    }

    public function logDeleteRequestRejected(User $admin, User $target): AuditLog
    {
        return $this->log(AuditLog::ACTION_DELETE_REQUEST_REJECTED, $admin, $target);
    }

    public function logSoftDelete(User $admin, User $target): AuditLog
    {
        return $this->log(AuditLog::ACTION_SOFT_DELETE, $admin, $target);
    }

    public function logRestore(User $admin, User $target): AuditLog
    {
        return $this->log(AuditLog::ACTION_RESTORE, $admin, $target);
    }

    public function logHardDelete(User $admin, int $targetUserId, string $targetEmail): AuditLog
    {
        return $this->log(AuditLog::ACTION_HARD_DELETE, $admin, null, [
            'deleted_user_id' => $targetUserId,
            'deleted_user_email' => $targetEmail,
        ]);
    }

    public function logRolesUpdated(User $admin, User $target, array $oldRoles, array $newRoles): AuditLog
    {
        return $this->log(AuditLog::ACTION_ROLES_UPDATED, $admin, $target, [
            'old_roles' => $oldRoles,
            'new_roles' => $newRoles,
        ]);
    }

    public function logSuspended(User $admin, User $target, ?string $reason, ?\DateTimeInterface $until): AuditLog
    {
        return $this->log(AuditLog::ACTION_SUSPENDED, $admin, $target, [
            'reason' => $reason,
            'until' => $until?->format('c'),
        ]);
    }

    public function logUnsuspended(User $admin, User $target): AuditLog
    {
        return $this->log(AuditLog::ACTION_UNSUSPENDED, $admin, $target);
    }

    public function logImpersonationStart(User $admin, User $target, string $sessionId): AuditLog
    {
        return $this->log(AuditLog::ACTION_IMPERSONATION_START, $admin, $target, [
            'session_id' => $sessionId,
        ]);
    }

    public function logImpersonationStop(User $admin, User $target, string $sessionId): AuditLog
    {
        return $this->log(AuditLog::ACTION_IMPERSONATION_STOP, $admin, $target, [
            'session_id' => $sessionId,
        ]);
    }

    public function logEmailVerified(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_EMAIL_VERIFIED, $user, $user);
    }

    public function logEmailVerificationSent(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_EMAIL_VERIFICATION_SENT, $user, $user);
    }

    public function logDataExported(User $user): AuditLog
    {
        return $this->log(AuditLog::ACTION_DATA_EXPORTED, $user, $user);
    }

    public function logProfileUpdated(User $user, array $changedFields): AuditLog
    {
        return $this->log(AuditLog::ACTION_PROFILE_UPDATED, $user, $user, [
            'changed_fields' => $changedFields,
        ]);
    }
}
