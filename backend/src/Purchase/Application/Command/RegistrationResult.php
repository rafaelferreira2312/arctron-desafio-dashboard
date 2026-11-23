<?php

declare(strict_types=1);

namespace App\Purchase\Application\Command;

final readonly class RegistrationResult
{
    private function __construct(
        public string $reference,
        public bool $registered,
    ) {
    }

    public static function registered(string $reference): self
    {
        return new self($reference, true);
    }

    public static function alreadyRegistered(string $reference): self
    {
        return new self($reference, false);
    }
}
