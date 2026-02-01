<?php

namespace App\Entity;

use App\Repository\AccountActionRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountActionRequestRepository::class)]
#[ORM\Table(name: 'account_action_request')]
#[ORM\Index(name: 'IDX_AAR_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_AAR_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_AAR_TYPE', columns: ['type'])]
#[ORM\Index(name: 'IDX_AAR_CREATED_AT', columns: ['created_at'])]
class AccountActionRequest
{
    public const TYPE_DELETE_ACCOUNT = 'DELETE_ACCOUNT';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $handledBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function setHandledAt(?\DateTimeImmutable $handledAt): static
    {
        $this->handledAt = $handledAt;

        return $this;
    }

    public function getHandledBy(): ?User
    {
        return $this->handledBy;
    }

    public function setHandledBy(?User $handledBy): static
    {
        $this->handledBy = $handledBy;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function approve(User $admin): static
    {
        $this->status = self::STATUS_APPROVED;
        $this->handledAt = new \DateTimeImmutable();
        $this->handledBy = $admin;

        return $this;
    }

    public function reject(User $admin, ?string $message = null): static
    {
        $this->status = self::STATUS_REJECTED;
        $this->handledAt = new \DateTimeImmutable();
        $this->handledBy = $admin;
        $this->message = $message;

        return $this;
    }
}
