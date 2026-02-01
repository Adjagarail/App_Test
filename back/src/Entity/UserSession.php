<?php

namespace App\Entity;

use App\Repository\UserSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSessionRepository::class)]
#[ORM\Table(name: 'user_session')]
#[ORM\Index(name: 'IDX_SESSION_USER', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_SESSION_ACTIVE', columns: ['ended_at'])]
#[ORM\Index(name: 'IDX_SESSION_LAST_SEEN', columns: ['last_seen_at'])]
#[ORM\Index(name: 'IDX_SESSION_JTI', columns: ['token_jti'])]
class UserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tokenJti = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSeenAt = new \DateTimeImmutable();
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;
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

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;
        return $this;
    }

    public function getTokenJti(): ?string
    {
        return $this->tokenJti;
    }

    public function setTokenJti(?string $tokenJti): static
    {
        $this->tokenJti = $tokenJti;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->endedAt === null;
    }

    public function end(): static
    {
        $this->endedAt = new \DateTimeImmutable();
        return $this;
    }

    public function updateLastSeen(): static
    {
        $this->lastSeenAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Check if session was active within the given minutes.
     */
    public function wasActiveWithinMinutes(int $minutes): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $threshold = new \DateTimeImmutable("-{$minutes} minutes");
        return $this->lastSeenAt >= $threshold;
    }
}
