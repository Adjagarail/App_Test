<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check if user is deleted
        if ($user->isDeleted()) {
            throw new CustomUserMessageAccountStatusException('Ce compte a été supprimé.');
        }

        // Check if user is suspended
        if ($user->isCurrentlySuspended()) {
            $message = 'Votre compte est suspendu.';

            if ($user->getSuspendedUntil() !== null) {
                $message .= ' Suspension jusqu\'au ' . $user->getSuspendedUntil()->format('d/m/Y H:i');
            }

            if ($user->getSuspensionReason() !== null) {
                $message .= ' Raison : ' . $user->getSuspensionReason();
            }

            throw new CustomUserMessageAccountStatusException($message);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Auto-unsuspend if suspension has expired
        if ($user instanceof User && $user->isSuspended() && !$user->isCurrentlySuspended()) {
            $user->unsuspend();
        }
    }
}
