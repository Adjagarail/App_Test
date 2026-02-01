<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly UserRepository $userRepository
    ) {
    }

    public function create(User $recipient, string $type, array $payload): Notification
    {
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setPayload($payload);

        $this->notificationRepository->save($notification, true);

        return $notification;
    }

    public function notifyAccountDeleteRequested(User $recipient, User $requester): Notification
    {
        return $this->create($recipient, Notification::TYPE_ACCOUNT_DELETE_REQUESTED, [
            'title' => 'Nouvelle demande de suppression',
            'message' => sprintf('%s a demandé la suppression de son compte', $requester->getEmail()),
            'user_id' => $requester->getId(),
            'user_email' => $requester->getEmail(),
            'user_name' => $requester->getNomComplet(),
        ]);
    }

    public function notifyAccountDeleteApproved(User $recipient): Notification
    {
        return $this->create($recipient, Notification::TYPE_ACCOUNT_DELETE_APPROVED, [
            'title' => 'Demande de suppression approuvée',
            'message' => 'Votre demande de suppression de compte a été approuvée',
        ]);
    }

    public function notifyAccountDeleteRejected(User $recipient, ?string $reason = null): Notification
    {
        return $this->create($recipient, Notification::TYPE_ACCOUNT_DELETE_REJECTED, [
            'title' => 'Demande de suppression refusée',
            'message' => 'Votre demande de suppression de compte a été refusée',
            'reason' => $reason,
        ]);
    }

    public function notifyPasswordChanged(User $recipient): Notification
    {
        return $this->create($recipient, Notification::TYPE_PASSWORD_CHANGED, [
            'title' => 'Mot de passe modifié',
            'message' => 'Votre mot de passe a été modifié avec succès',
        ]);
    }

    public function notifyRolesUpdated(User $recipient, array $newRoles): Notification
    {
        return $this->create($recipient, Notification::TYPE_ROLES_UPDATED, [
            'title' => 'Rôles mis à jour',
            'message' => 'Vos rôles ont été modifiés',
            'roles' => $newRoles,
        ]);
    }

    public function notifyAccountSuspended(User $recipient, ?string $reason = null, ?\DateTimeInterface $until = null): Notification
    {
        $message = 'Votre compte a été suspendu';
        if ($until !== null) {
            $message .= ' jusqu\'au ' . $until->format('d/m/Y H:i');
        }

        return $this->create($recipient, Notification::TYPE_ACCOUNT_SUSPENDED, [
            'title' => 'Compte suspendu',
            'message' => $message,
            'reason' => $reason,
            'until' => $until?->format('c'),
        ]);
    }

    public function notifyAccountUnsuspended(User $recipient): Notification
    {
        return $this->create($recipient, Notification::TYPE_ACCOUNT_UNSUSPENDED, [
            'title' => 'Compte réactivé',
            'message' => 'Votre compte a été réactivé',
        ]);
    }

    public function notifyEmailVerified(User $recipient): Notification
    {
        return $this->create($recipient, Notification::TYPE_EMAIL_VERIFIED, [
            'title' => 'Email vérifié',
            'message' => 'Votre adresse email a été vérifiée avec succès',
        ]);
    }

    public function notifyNewLogin(User $recipient, string $ip, string $userAgent): Notification
    {
        return $this->create($recipient, Notification::TYPE_NEW_LOGIN, [
            'title' => 'Nouvelle connexion',
            'message' => sprintf('Nouvelle connexion détectée depuis %s', $ip),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Notify all admins about an event.
     *
     * @param User[] $admins
     * @return Notification[]
     */
    public function notifyAdminUsers(array $admins, string $type, array $payload): array
    {
        $notifications = [];

        foreach ($admins as $admin) {
            $notifications[] = $this->create($admin, $type, $payload);
        }

        return $notifications;
    }

    /**
     * Notify all admins (ROLE_ADMIN and ROLE_SUPER_ADMIN) with a simple notification.
     *
     * @return Notification[]
     */
    public function notifyAdmins(
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null
    ): array {
        $admins = $this->userRepository->findAdmins();
        $notifications = [];

        foreach ($admins as $admin) {
            $notifications[] = $this->create($admin, $type, [
                'title' => $title,
                'message' => $message,
                'link' => $link,
            ]);
        }

        return $notifications;
    }
}
