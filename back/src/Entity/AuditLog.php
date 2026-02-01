<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'IDX_AUDIT_ACTOR', columns: ['actor_user_id'])]
#[ORM\Index(name: 'IDX_AUDIT_TARGET', columns: ['target_user_id'])]
#[ORM\Index(name: 'IDX_AUDIT_ACTION', columns: ['action'])]
#[ORM\Index(name: 'IDX_AUDIT_CREATED_AT', columns: ['created_at'])]
class AuditLog
{
    // Authentication actions
    public const ACTION_LOGIN = 'LOGIN';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_LOGIN_FAILED = 'LOGIN_FAILED';

    // Password actions
    public const ACTION_PASSWORD_CHANGE = 'PASSWORD_CHANGE';
    public const ACTION_PASSWORD_RESET_REQUEST = 'PASSWORD_RESET_REQUEST';
    public const ACTION_PASSWORD_RESET_COMPLETE = 'PASSWORD_RESET_COMPLETE';

    // Account actions
    public const ACTION_DELETE_REQUEST = 'DELETE_REQUEST';
    public const ACTION_DELETE_REQUEST_APPROVED = 'DELETE_REQUEST_APPROVED';
    public const ACTION_DELETE_REQUEST_REJECTED = 'DELETE_REQUEST_REJECTED';
    public const ACTION_SOFT_DELETE = 'SOFT_DELETE';
    public const ACTION_RESTORE = 'RESTORE';
    public const ACTION_HARD_DELETE = 'HARD_DELETE';

    // Role actions
    public const ACTION_ROLES_UPDATED = 'ROLES_UPDATED';

    // Suspension actions
    public const ACTION_SUSPENDED = 'SUSPENDED';
    public const ACTION_UNSUSPENDED = 'UNSUSPENDED';

    // Impersonation actions
    public const ACTION_IMPERSONATION_START = 'IMPERSONATION_START';
    public const ACTION_IMPERSONATION_STOP = 'IMPERSONATION_STOP';

    // Email verification
    public const ACTION_EMAIL_VERIFIED = 'EMAIL_VERIFIED';
    public const ACTION_EMAIL_VERIFICATION_SENT = 'EMAIL_VERIFICATION_SENT';

    // Data export
    public const ACTION_DATA_EXPORTED = 'DATA_EXPORTED';

    // Profile
    public const ACTION_PROFILE_UPDATED = 'PROFILE_UPDATED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actorUser = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $targetUser = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }

    public function setActorUser(?User $actorUser): static
    {
        $this->actorUser = $actorUser;

        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(?User $targetUser): static
    {
        $this->targetUser = $targetUser;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

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
}
