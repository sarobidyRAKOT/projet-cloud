<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "fa_codes")]
class FaCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $code_id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "user_id", onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(type: "string", length: 6)]
    private string $code;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $expires_at;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $is_used = false;

    // Getters and Setters
    public function getCodeId(): int
    {
        return $this->code_id;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expires_at;
    }

    public function setExpiresAt(\DateTimeInterface $expires_at): self
    {
        $this->expires_at = $expires_at;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->is_used;
    }

    public function setUsed(bool $is_used): self
    {
        $this->is_used = $is_used;
        return $this;
    }
}
