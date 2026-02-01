<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\Index(name: 'IDX_USER_DATE_INSCRIPTION', columns: ['date_inscription'])]
#[ORM\Index(name: 'IDX_USER_DERNIERE_CONNEXION', columns: ['date_derniere_connexion'])]
#[ORM\Index(name: 'IDX_USER_DELETED_AT', columns: ['deleted_at'])]
#[ORM\Index(name: 'IDX_USER_EMAIL_VERIFIED', columns: ['is_email_verified'])]
#[ORM\Index(name: 'IDX_USER_SUSPENDED', columns: ['is_suspended'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $NomComplet = null;

    // Feature 1: Dates de connexion
    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateInscription = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateDerniereConnexion = null;

    // Feature 6: Soft delete
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    // Feature 7: Email verification
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isEmailVerified = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    // Feature 11: Suspension
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isSuspended = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $suspendedUntil = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $suspensionReason = null;

    public function __construct()
    {
        $this->dateInscription = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getNomComplet(): ?string
    {
        return $this->NomComplet;
    }

    public function setNomComplet(?string $NomComplet): static
    {
        $this->NomComplet = $NomComplet;

        return $this;
    }

    // Feature 1: Getters/Setters for dates
    public function getDateInscription(): ?\DateTimeImmutable
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeImmutable $dateInscription): static
    {
        $this->dateInscription = $dateInscription;

        return $this;
    }

    public function getDateDerniereConnexion(): ?\DateTimeImmutable
    {
        return $this->dateDerniereConnexion;
    }

    public function setDateDerniereConnexion(?\DateTimeImmutable $dateDerniereConnexion): static
    {
        $this->dateDerniereConnexion = $dateDerniereConnexion;

        return $this;
    }

    // Feature 6: Soft delete
    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }

    public function restore(): static
    {
        $this->deletedAt = null;

        return $this;
    }

    // Feature 7: Email verification
    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): static
    {
        $this->isEmailVerified = $isEmailVerified;

        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;

        return $this;
    }

    public function verifyEmail(): static
    {
        $this->isEmailVerified = true;
        $this->emailVerifiedAt = new \DateTimeImmutable();

        return $this;
    }

    // Feature 11: Suspension
    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function setIsSuspended(bool $isSuspended): static
    {
        $this->isSuspended = $isSuspended;

        return $this;
    }

    public function getSuspendedUntil(): ?\DateTimeImmutable
    {
        return $this->suspendedUntil;
    }

    public function setSuspendedUntil(?\DateTimeImmutable $suspendedUntil): static
    {
        $this->suspendedUntil = $suspendedUntil;

        return $this;
    }

    public function getSuspensionReason(): ?string
    {
        return $this->suspensionReason;
    }

    public function setSuspensionReason(?string $suspensionReason): static
    {
        $this->suspensionReason = $suspensionReason;

        return $this;
    }

    /**
     * Check if the user is currently suspended (taking into account suspendedUntil).
     */
    public function isCurrentlySuspended(): bool
    {
        if (!$this->isSuspended) {
            return false;
        }

        // If no end date, suspension is permanent
        if ($this->suspendedUntil === null) {
            return true;
        }

        // Check if suspension has expired
        return $this->suspendedUntil > new \DateTimeImmutable();
    }

    public function suspend(?string $reason = null, ?\DateTimeImmutable $until = null): static
    {
        $this->isSuspended = true;
        $this->suspensionReason = $reason;
        $this->suspendedUntil = $until;

        return $this;
    }

    public function unsuspend(): static
    {
        $this->isSuspended = false;
        $this->suspensionReason = null;
        $this->suspendedUntil = null;

        return $this;
    }

    /**
     * Check if user can login (not deleted and not suspended).
     */
    public function canLogin(): bool
    {
        return !$this->isDeleted() && !$this->isCurrentlySuspended();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }
}
