<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "login_attempts")]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $attempt_id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "user_id", onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $attempt_time;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $is_successful = false;

    #[ORM\Column(type: "string", length: 45, nullable: true)]
    private ?string $ip_address = null;

    // Getters and Setters
    public function getAttemptId(): int
    {
        return $this->attempt_id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAttemptTime(): \DateTimeInterface
    {
        return $this->attempt_time;
    }

    public function setAttemptTime(\DateTimeInterface $attempt_time): self
    {
        $this->attempt_time = $attempt_time;
        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->is_successful;
    }

    public function setSuccessful(bool $is_successful): self
    {
        $this->is_successful = $is_successful;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ip_address;
    }

    public function setIpAddress(?string $ip_address): self
    {
        $this->ip_address = $ip_address;
        return $this;
    }
}
