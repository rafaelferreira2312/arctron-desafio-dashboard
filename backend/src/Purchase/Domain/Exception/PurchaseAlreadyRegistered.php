<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Exception;

final class PurchaseAlreadyRegistered extends \RuntimeException
{
    public static function withReference(string $reference): self
    {
        return new self(sprintf('A compra "%s" já foi processada.', $reference));
    }
}
