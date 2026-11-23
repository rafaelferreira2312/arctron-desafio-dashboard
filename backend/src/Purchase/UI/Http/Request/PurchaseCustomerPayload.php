<?php

declare(strict_types=1);

namespace App\Purchase\UI\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PurchaseCustomerPayload
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name = '',

        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public string $email = '',

        #[Assert\NotBlank]
        public string $cpf = '',
    ) {
    }
}
