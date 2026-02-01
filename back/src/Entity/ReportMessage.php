<?php

namespace App\Entity;

use App\Repository\ReportMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportMessageRepository::class)]
#[ORM\Table(name: 'report_message')]
#[ORM\Index(name: 'IDX_REPORT_CREATED_AT', columns: ['created_at'])]
#[ORM\Index(name: 'IDX_REPORT_STATUS', columns: ['status'])]
class ReportMessage
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_CLOSED = 'CLOSED';

    public const TYPE_USER_MESSAGE = 'USER_MESSAGE';
    public const TYPE_ADMIN_RESPONSE = 'ADMIN_RESPONSE';
    public const TYPE_SYSTEM = 'SYSTEM';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $assignedAdmin = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_USER_MESSAGE;

    #[ORM\Column(length: 50)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $parentId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $threadId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getAssignedAdmin(): ?User
    {
        return $this->assignedAdmin;
    }

    public function setAssignedAdmin(?User $assignedAdmin): static
    {
        $this->assignedAdmin = $assignedAdmin;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): static
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getThreadId(): ?int
    {
        return $this->threadId;
    }

    public function setThreadId(?int $threadId): static
    {
        $this->threadId = $threadId;
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

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        if ($isRead && !$this->readAt) {
            $this->readAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function isFromAdmin(): bool
    {
        return $this->type === self::TYPE_ADMIN_RESPONSE;
    }

    public function isFromUser(): bool
    {
        return $this->type === self::TYPE_USER_MESSAGE;
    }
}
