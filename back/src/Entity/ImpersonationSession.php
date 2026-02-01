<?php

namespace App\Entity;

use App\Repository\ImpersonationSessionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ImpersonationSessionRepository::class)]
#[ORM\Table(name: 'impersonation_session')]
#[ORM\Index(name: 'IDX_IMP_IMPERSONATOR', columns: ['impersonator_id'])]
#[ORM\Index(name: 'IDX_IMP_TARGET', columns: ['target_user_id'])]
#[ORM\Index(name: 'IDX_IMP_JTI', columns: ['token_jti'])]
#[ORM\Index(name: 'IDX_IMP_EXPIRES_AT', columns: ['expires_at'])]
#[ORM\Index(name: 'IDX_IMP_ACTIVE', columns: ['impersonator_id', 'revoked_at'])]
class ImpersonationSession
{
    private const DEFAULT_TTL_MINUTES = 10;

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $impersonator = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $targetUser = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $tokenJti = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+' . self::DEFAULT_TTL_MINUTES . ' minutes');
        $this->tokenJti = bin2hex(random_bytes(32));
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getImpersonator(): ?User
    {
        return $this->impersonator;
    }

    public function setImpersonator(?User $impersonator): static
    {
        $this->impersonator = $impersonator;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getTokenJti(): ?string
    {
        return $this->tokenJti;
    }

    public function setTokenJti(string $tokenJti): static
    {
        $this->tokenJti = $tokenJti;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isActive(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }

    public function revoke(): static
    {
        $this->revokedAt = new \DateTimeImmutable();

        return $this;
    }
}
