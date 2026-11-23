<?php

declare(strict_types=1);

namespace App\Purchase\UI\Http\Request;

use App\Purchase\Application\Command\PurchasedProduct;
use App\Purchase\Application\Command\RegisterPurchase;
use App\Purchase\Domain\Model\Purchase;
use App\Purchase\Domain\Model\PurchaseSource;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corpo do POST /api/purchases. A validação aqui é de formato;
 * as regras de negócio ficam no domínio.
 */
final readonly class RegisterPurchasePayload
{
    /**
     * @param PurchaseProductPayload[] $products
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: Purchase::REFERENCE_MAX_LENGTH)]
        public string $reference = '',

        #[Assert\NotBlank]
        public float|string|null $amount = null,

        public float|string|null $discount = null,

        #[Assert\NotBlank]
        public string $date = '',

        #[Assert\Count(min: 1, minMessage: 'Informe pelo menos um produto.')]
        #[Assert\Valid]
        public array $products = [],

        #[Assert\NotNull]
        #[Assert\Valid]
        public ?PurchaseCustomerPayload $customer = null,

        #[Assert\Length(max: 2000)]
        public ?string $notes = null,
    ) {
    }

    public function toCommand(): RegisterPurchase
    {
        return new RegisterPurchase(
            reference: $this->reference,
            amount: self::decimal($this->amount),
            discount: self::decimal($this->discount ?? '0'),
            purchasedAt: $this->date,
            customerName: $this->customer?->name ?? '',
            customerEmail: $this->customer?->email ?? '',
            customerCpf: $this->customer?->cpf ?? '',
            products: array_map(
                static fn (PurchaseProductPayload $product): PurchasedProduct => new PurchasedProduct(
                    $product->name,
                    $product->quantity,
                    self::decimal($product->unitPrice),
                ),
                $this->products,
            ),
            notes: $this->notes,
            source: PurchaseSource::Api,
        );
    }

    private static function decimal(float|string|null $value): string
    {
        return is_float($value) ? number_format($value, 2, '.', '') : (string) $value;
    }
}
