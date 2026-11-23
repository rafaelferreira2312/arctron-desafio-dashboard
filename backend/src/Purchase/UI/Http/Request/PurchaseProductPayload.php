<?php

declare(strict_types=1);

namespace App\Purchase\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PurchaseProductPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name = '',

        #[Assert\Positive]
        public int $quantity = 0,

        #[Assert\NotBlank]
        public float|string|null $unitPrice = null,
    ) {
    }
}
