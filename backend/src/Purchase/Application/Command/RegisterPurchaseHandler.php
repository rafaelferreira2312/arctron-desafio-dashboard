<?php

declare(strict_types=1);

namespace App\Purchase\Application\Command;

use App\Purchase\Domain\Exception\PurchaseAlreadyRegistered;
use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Customer;
use App\Purchase\Domain\Model\Money;
use App\Purchase\Domain\Model\Product;
use App\Purchase\Domain\Model\Purchase;
use App\Purchase\Domain\Model\PurchaseLine;
use App\Purchase\Domain\Repository\CustomerRepository;
use App\Purchase\Domain\Repository\ProductRepository;
use App\Purchase\Domain\Repository\PurchaseRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RegisterPurchaseHandler
{
    private \DateTimeZone $timezone;

    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly CustomerRepository $customers,
        private readonly ProductRepository $products,
        #[Autowire('%env(APP_TIMEZONE)%')]
        string $timezone = 'America/Sao_Paulo',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->timezone = new \DateTimeZone($timezone);
    }

    public function __invoke(RegisterPurchase $command): RegistrationResult
    {
        $reference = trim($command->reference);

        // Regra do enunciado: o segundo envio com a mesma referência é ignorado.
        if ($this->purchases->existsWithReference($reference)) {
            return $this->ignored($reference);
        }

        $purchase = Purchase::register(
            reference: $reference,
            customer: $this->resolveCustomer($command),
            amount: Money::fromDecimal($command->amount),
            discount: Money::fromDecimal($command->discount),
            purchasedAt: PurchaseDateParser::parse($command->purchasedAt, $this->timezone),
            lines: $this->buildLines($command->products),
            notes: $command->notes,
            source: $command->source,
        );

        try {
            $this->purchases->save($purchase);
        } catch (PurchaseAlreadyRegistered) {
            // Duas requisições com a mesma referência ao mesmo tempo: a constraint do banco decide.
            return $this->ignored($reference);
        }

        $this->logger->info('Compra registrada', [
            'reference' => $reference,
            'source' => $command->source->value,
        ]);

        return RegistrationResult::registered($reference);
    }

    private function resolveCustomer(RegisterPurchase $command): Customer
    {
        $cpf = Cpf::fromString($command->customerCpf);
        $customer = $this->customers->findByCpf($cpf);

        if ($customer === null) {
            return Customer::register($command->customerName, $command->customerEmail, $cpf);
        }

        $customer->updateContact($command->customerName, $command->customerEmail);

        return $customer;
    }

    /**
     * @param list<PurchasedProduct> $products
     *
     * @return list<PurchaseLine>
     */
    private function buildLines(array $products): array
    {
        // Evita criar o mesmo produto novo duas vezes quando ele aparece repetido na compra.
        $resolved = [];
        $lines = [];

        foreach ($products as $item) {
            $key = Product::normalize($item->name);
            $resolved[$key] ??= $this->products->findByName($item->name) ?? Product::named($item->name);

            $lines[] = new PurchaseLine($resolved[$key], $item->quantity, Money::fromDecimal($item->unitPrice));
        }

        return $lines;
    }

    private function ignored(string $reference): RegistrationResult
    {
        $this->logger->info('Compra ignorada, referência já processada', ['reference' => $reference]);

        return RegistrationResult::alreadyRegistered($reference);
    }
}
