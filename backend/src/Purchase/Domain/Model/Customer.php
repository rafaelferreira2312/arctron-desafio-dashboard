<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

use App\Purchase\Domain\Exception\InvalidPurchase;

class Customer
{
    private ?int $id = null;
    private string $name;
    private string $email;
    private Cpf $cpf;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(string $name, string $email, Cpf $cpf)
    {
        $this->changeContact($name, $email);
        $this->cpf = $cpf;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public static function register(string $name, string $email, Cpf $cpf): self
    {
        return new self($name, $email, $cpf);
    }

    /**
     * O CPF identifica o cliente. Nome e e-mail podem mudar entre uma compra e outra,
     * então sempre fica o dado mais recente.
     */
    public function updateContact(string $name, string $email): void
    {
        $before = [$this->name, $this->email];
        $this->changeContact($name, $email);

        if ($before !== [$this->name, $this->email]) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    private function changeContact(string $name, string $email): void
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $email = mb_strtolower(trim($email));

        if ($name === '') {
            throw InvalidPurchase::emptyCustomerName();
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidPurchase::invalidEmail($email);
        }

        $this->name = $name;
        $this->email = $email;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function cpf(): Cpf
    {
        return $this->cpf;
    }
}
