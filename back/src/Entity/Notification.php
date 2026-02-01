<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\Index(name: 'IDX_NOTIF_RECIPIENT', columns: ['recipient_id'])]
#[ORM\Index(name: 'IDX_NOTIF_READ', columns: ['is_read'])]
#[ORM\Index(name: 'IDX_NOTIF_CREATED_AT', columns: ['created_at'])]
#[ORM\Index(name: 'IDX_NOTIF_RECIPIENT_READ_DATE', columns: ['recipient_id', 'is_read', 'created_at'])]
class Notification
{
    // Notification types
    public const TYPE_ACCOUNT_DELETE_REQUESTED = 'ACCOUNT_DELETE_REQUESTED';
    public const TYPE_ACCOUNT_DELETE_APPROVED = 'ACCOUNT_DELETE_APPROVED';
    public const TYPE_ACCOUNT_DELETE_REJECTED = 'ACCOUNT_DELETE_REJECTED';
    public const TYPE_PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    public const TYPE_ROLES_UPDATED = 'ROLES_UPDATED';
    public const TYPE_ACCOUNT_SUSPENDED = 'ACCOUNT_SUSPENDED';
    public const TYPE_ACCOUNT_UNSUSPENDED = 'ACCOUNT_UNSUSPENDED';
    public const TYPE_EMAIL_VERIFIED = 'EMAIL_VERIFIED';
    public const TYPE_NEW_LOGIN = 'NEW_LOGIN';
    public const TYPE_SYSTEM = 'SYSTEM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        if ($isRead && $this->readAt === null) {
            $this->readAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function markAsRead(): static
    {
        $this->isRead = true;
        $this->readAt = new \DateTimeImmutable();

        return $this;
    }
}
