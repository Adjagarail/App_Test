<?php

namespace App\Controller;

use App\Entity\ReportMessage;
use App\Entity\User;
use App\Repository\ReportMessageRepository;
use App\Service\MercureService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ReportChatController extends AbstractController
{
    public function __construct(
        private readonly ReportMessageRepository $reportMessageRepository,
        private readonly MercureService $mercureService,
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Get all report threads (admin only).
     */
    #[Route('/admin/reports', name: 'admin_reports_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function listReports(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $status = $request->query->get('status');

        $threads = $this->reportMessageRepository->findThreads($page, $limit, $status);
        $total = $this->reportMessageRepository->countThreads($status);

        return new JsonResponse([
            'threads' => array_map(fn(ReportMessage $m) => $this->formatThread($m), $threads),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
            'unreadCount' => $this->reportMessageRepository->countUnreadForAdmins(),
        ]);
    }

    /**
     * Get messages in a thread.
     */
    #[Route('/reports/{threadId}', name: 'report_thread', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getThread(int $threadId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $messages = $this->reportMessageRepository->findByThread($threadId);

        if (empty($messages)) {
            return new JsonResponse(['error' => 'Thread not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user has access to this thread
        $firstMessage = $messages[0];
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $firstMessage->getSender()->getId() === $user->getId();

        if (!$isAdmin && !$isOwner) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Mark messages as read
        foreach ($messages as $message) {
            if (!$message->isRead()) {
                if ($isAdmin && $message->isFromUser()) {
                    $message->setIsRead(true);
                } elseif ($isOwner && $message->isFromAdmin()) {
                    $message->setIsRead(true);
                }
            }
        }
        $this->reportMessageRepository->save($messages[0], true);

        return new JsonResponse([
            'thread' => $this->formatThread($firstMessage),
            'messages' => array_map(fn(ReportMessage $m) => $this->formatMessage($m), $messages),
        ]);
    }

    /**
     * Create a new report (user).
     */
    #[Route('/me/reports', name: 'create_report', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function createReport(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $subject = trim($data['subject'] ?? '');
        $message = trim($data['message'] ?? '');

        if (empty($message)) {
            return new JsonResponse(['error' => 'Message is required'], Response::HTTP_BAD_REQUEST);
        }

        $report = new ReportMessage();
        $report->setSender($user);
        $report->setSubject($subject ?: 'Rapport');
        $report->setMessage($message);
        $report->setType(ReportMessage::TYPE_USER_MESSAGE);
        $report->setStatus(ReportMessage::STATUS_OPEN);

        $this->reportMessageRepository->save($report, true);

        // Set threadId to its own ID (first message in thread)
        $report->setThreadId($report->getId());
        $this->reportMessageRepository->save($report, true);

        // Publish to Mercure for real-time admin notification (after save to have ID)
        $this->mercureService->publishReportMessage(
            $user,
            $report->getId(),
            $message,
            $report->getId()
        );

        // Create notification for admins
        $this->notificationService->notifyAdmins(
            'Nouveau rapport',
            "Nouveau rapport de {$user->getEmail()}: " . ($subject ?: 'Rapport'),
            'info',
            '/dashboard'
        );

        return new JsonResponse([
            'message' => 'Report created successfully',
            'report' => $this->formatThread($report),
        ], Response::HTTP_CREATED);
    }

    /**
     * Reply to a report thread.
     */
    #[Route('/reports/{threadId}/reply', name: 'reply_report', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function replyToReport(int $threadId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $messageText = trim($data['message'] ?? '');

        if (empty($messageText)) {
            return new JsonResponse(['error' => 'Message is required'], Response::HTTP_BAD_REQUEST);
        }

        // Find the thread
        $thread = $this->reportMessageRepository->find($threadId);
        if (!$thread || $thread->getParentId() !== null) {
            return new JsonResponse(['error' => 'Thread not found'], Response::HTTP_NOT_FOUND);
        }

        // Check access
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $thread->getSender()->getId() === $user->getId();

        if (!$isAdmin && !$isOwner) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $reply = new ReportMessage();
        $reply->setSender($user);
        $reply->setMessage($messageText);
        $reply->setParentId($threadId);
        $reply->setThreadId($threadId);
        $reply->setSubject($thread->getSubject());

        if ($isAdmin) {
            $reply->setType(ReportMessage::TYPE_ADMIN_RESPONSE);
            $reply->setAssignedAdmin($user);

            // Update thread status
            if ($thread->getStatus() === ReportMessage::STATUS_OPEN) {
                $thread->setStatus(ReportMessage::STATUS_IN_PROGRESS);
                $thread->setAssignedAdmin($user);
            }
        } else {
            $reply->setType(ReportMessage::TYPE_USER_MESSAGE);
        }

        // Save first to get the ID
        $this->reportMessageRepository->save($reply, true);
        $this->reportMessageRepository->save($thread, true);

        // Publish to Mercure AFTER save to have the ID
        if ($isAdmin) {
            // Notify user via Mercure
            $this->mercureService->publishReportResponse(
                $threadId,
                $thread->getSender()->getId(),
                $user,
                $messageText,
                $reply->getId()
            );
        } else {
            // Notify admins
            $this->mercureService->publishReportMessage(
                $user,
                $threadId,
                $messageText,
                $reply->getId()
            );
        }

        return new JsonResponse([
            'message' => 'Reply sent successfully',
            'reply' => $this->formatMessage($reply),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update thread status (admin only).
     */
    #[Route('/admin/reports/{threadId}/status', name: 'update_report_status', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(int $threadId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;

        $validStatuses = [
            ReportMessage::STATUS_OPEN,
            ReportMessage::STATUS_IN_PROGRESS,
            ReportMessage::STATUS_RESOLVED,
            ReportMessage::STATUS_CLOSED,
        ];

        if (!in_array($status, $validStatuses)) {
            return new JsonResponse(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $thread = $this->reportMessageRepository->find($threadId);
        if (!$thread || $thread->getParentId() !== null) {
            return new JsonResponse(['error' => 'Thread not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $thread->setStatus($status);
        if (!$thread->getAssignedAdmin()) {
            $thread->setAssignedAdmin($admin);
        }

        // Add system message
        $systemMessage = new ReportMessage();
        $systemMessage->setSender($admin);
        $systemMessage->setMessage("Statut mis à jour: {$status}");
        $systemMessage->setType(ReportMessage::TYPE_SYSTEM);
        $systemMessage->setParentId($threadId);
        $systemMessage->setThreadId($threadId);

        $this->reportMessageRepository->save($thread, true);
        $this->reportMessageRepository->save($systemMessage, true);

        return new JsonResponse([
            'message' => 'Status updated successfully',
            'thread' => $this->formatThread($thread),
        ]);
    }

    /**
     * Get my reports (user).
     */
    #[Route('/me/reports', name: 'my_reports', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getMyReports(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $threads = $this->reportMessageRepository->findUserThreads($user, $page, $limit);

        return new JsonResponse([
            'threads' => array_map(fn(ReportMessage $m) => $this->formatThread($m), $threads),
            'unreadCount' => $this->reportMessageRepository->countUnreadForUser($user),
        ]);
    }

    /**
     * Get unread count for dashboard.
     */
    #[Route('/admin/reports/unread-count', name: 'admin_reports_unread', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getUnreadCount(): JsonResponse
    {
        return new JsonResponse([
            'unreadCount' => $this->reportMessageRepository->countUnreadForAdmins(),
        ]);
    }

    private function formatThread(ReportMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'subject' => $message->getSubject(),
            'status' => $message->getStatus(),
            'sender' => [
                'id' => $message->getSender()->getId(),
                'email' => $message->getSender()->getEmail(),
                'nomComplet' => $message->getSender()->getNomComplet(),
            ],
            'assignedAdmin' => $message->getAssignedAdmin() ? [
                'id' => $message->getAssignedAdmin()->getId(),
                'email' => $message->getAssignedAdmin()->getEmail(),
                'nomComplet' => $message->getAssignedAdmin()->getNomComplet(),
            ] : null,
            'lastMessage' => substr($message->getMessage(), 0, 100),
            'createdAt' => $message->getCreatedAt()->format('c'),
            'isRead' => $message->isRead(),
        ];
    }

    private function formatMessage(ReportMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'message' => $message->getMessage(),
            'type' => $message->getType(),
            'sender' => [
                'id' => $message->getSender()->getId(),
                'email' => $message->getSender()->getEmail(),
                'nomComplet' => $message->getSender()->getNomComplet(),
            ],
            'createdAt' => $message->getCreatedAt()->format('c'),
            'isRead' => $message->isRead(),
        ];
    }
}
