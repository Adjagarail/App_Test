<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercureService
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Publish active users count to all subscribed clients.
     */
    public function publishActiveUsersCount(): void
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT COUNT(DISTINCT username) as count FROM refresh_tokens WHERE valid > NOW()';
        $result = $conn->executeQuery($sql)->fetchAssociative();
        $activeSessions = $result ? (int) $result['count'] : 0;

        $update = new Update(
            'dashboard/active-users',
            json_encode([
                'type' => 'active_users_update',
                'count' => $activeSessions,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]),
            false // Public topic - anonymous allowed
        );

        $this->hub->publish($update);
    }

    /**
     * Publish a new report message to admins.
     * Includes threadId for proper tracking.
     */
    public function publishReportMessage(
        User $sender,
        int $threadId,
        string $message,
        int $messageId
    ): void {
        $update = new Update(
            'admin/reports',
            json_encode([
                'type' => 'new_report_message',
                'id' => $messageId,
                'threadId' => $threadId,
                'sender' => [
                    'id' => $sender->getId(),
                    'email' => $sender->getEmail(),
                    'nomComplet' => $sender->getNomComplet(),
                ],
                'message' => substr($message, 0, 100),
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]),
            false // Public topic - anonymous allowed
        );

        $this->hub->publish($update);
    }

    /**
     * Publish a response to a report - notifies the user who created the thread.
     * Uses user-specific topic for targeted notifications.
     */
    public function publishReportResponse(
        int $threadId,
        int $userId,
        User $admin,
        string $message,
        int $messageId
    ): void {
        $update = new Update(
            "user/{$userId}/reports",
            json_encode([
                'type' => 'report_response',
                'threadId' => $threadId,
                'messageId' => $messageId,
                'admin' => [
                    'id' => $admin->getId(),
                    'email' => $admin->getEmail(),
                    'nomComplet' => $admin->getNomComplet(),
                ],
                'message' => substr($message, 0, 100),
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]),
            false // Public topic - anonymous allowed
        );

        $this->hub->publish($update);
    }

    /**
     * Publish a notification to admins.
     */
    public function publishAdminNotification(
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null
    ): void {
        $update = new Update(
            'admin/notifications',
            json_encode([
                'type' => 'admin_notification',
                'notification' => [
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'link' => $link,
                ],
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]),
            false // Public topic - anonymous allowed
        );

        $this->hub->publish($update);
    }

    /**
     * Publish dashboard stats update.
     */
    public function publishDashboardStats(array $stats): void
    {
        $update = new Update(
            'dashboard/stats',
            json_encode([
                'type' => 'stats_update',
                'stats' => $stats,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ]),
            false // Public topic - anonymous allowed
        );

        $this->hub->publish($update);
    }
}
