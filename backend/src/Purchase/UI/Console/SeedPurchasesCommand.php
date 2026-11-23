<?php

declare(strict_types=1);

namespace App\Purchase\UI\Console;

use App\Purchase\Application\CommandBus;
use App\Purchase\Application\Command\PurchasedProduct;
use App\Purchase\Application\Command\RegisterPurchase;
use App\Purchase\Application\Command\RegistrationResult;
use App\Purchase\Domain\Model\Money;
use App\Purchase\Domain\Model\PurchaseSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Popula o banco com compras fictícias pra ter algo no dashboard.
 * Passa pelo mesmo command bus da API, então as regras de negócio valem aqui também.
 */
#[AsCommand(name: 'app:seed-purchases', description: 'Gera compras de exemplo para o dashboard')]
final class SeedPurchasesCommand extends Command
{
    private const FIRST_NAMES = ['Ana', 'Bruno', 'Carla', 'Diego', 'Eduarda', 'Felipe', 'Gabriela', 'Henrique', 'Isabela', 'João', 'Larissa', 'Marcos', 'Natália', 'Otávio', 'Patrícia', 'Rafael', 'Sabrina', 'Thiago', 'Vanessa', 'Wesley'];
    private const LAST_NAMES = ['Silva', 'Souza', 'Oliveira', 'Santos', 'Pereira', 'Costa', 'Rodrigues', 'Almeida', 'Nascimento', 'Lima', 'Araújo', 'Ferreira'];
    private const PRODUCTS = [
        'Teclado Mecânico ABNT2' => '349.90',
        'Mouse Sem Fio' => '89.90',
        'Monitor 27" IPS' => '1499.00',
        'Headset USB' => '219.90',
        'Webcam Full HD' => '259.00',
        'Cadeira Ergonômica' => '1890.00',
        'Suporte para Notebook' => '129.90',
        'Hub USB-C 7 em 1' => '199.90',
        'SSD NVMe 1TB' => '479.00',
        'Mousepad Grande' => '59.90',
        'Cabo HDMI 2m' => '39.90',
        'Luminária de Mesa LED' => '149.90',
    ];
    private const NOTES = [
        null,
        null,
        'Produto enviado para o cliente por SEDEX',
        'Produto enviado para o cliente por PAC',
        'Retirada na loja',
        'Cliente pediu embalagem para presente',
    ];

    public function __construct(private readonly CommandBus $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Quantidade de compras', '150')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Espalhar as compras pelos últimos N dias', '60')
            ->addOption('customers', null, InputOption::VALUE_REQUIRED, 'Quantidade de clientes diferentes', '25');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = max(1, (int) $input->getOption('count'));
        $days = max(1, (int) $input->getOption('days'));
        $customers = $this->customers(max(1, (int) $input->getOption('customers')));

        $created = 0;
        $io->progressStart($count);

        for ($i = 0; $i < $count; ++$i) {
            /** @var RegistrationResult $result */
            $result = $this->commandBus->handle($this->randomPurchase($customers[array_rand($customers)], $days));
            $created += $result->registered ? 1 : 0;
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('%d compras criadas.', $created));

        return Command::SUCCESS;
    }

    /**
     * @param array{name: string, email: string, cpf: string} $customer
     */
    private function randomPurchase(array $customer, int $days): RegisterPurchase
    {
        $products = [];
        $productsTotal = Money::zero();

        foreach ((array) array_rand(self::PRODUCTS, random_int(1, 3)) as $name) {
            $quantity = random_int(1, 3);
            $products[] = new PurchasedProduct($name, $quantity, self::PRODUCTS[$name]);
            $productsTotal = $productsTotal->add(Money::fromDecimal(self::PRODUCTS[$name])->multiply($quantity));
        }

        // ~30% das compras com desconto de 5 a 15%
        $discountCents = random_int(1, 10) <= 3 ? intdiv($productsTotal->cents() * random_int(5, 15), 100) : 0;

        $purchasedAt = (new \DateTimeImmutable())
            ->modify(sprintf('-%d days', random_int(0, $days - 1)))
            ->setTime(random_int(8, 22), random_int(0, 59));

        return new RegisterPurchase(
            reference: 'SEED-'.strtoupper(bin2hex(random_bytes(5))),
            amount: Money::format($productsTotal->cents() - $discountCents),
            discount: Money::format($discountCents),
            purchasedAt: $purchasedAt->format(\DateTimeInterface::ATOM),
            customerName: $customer['name'],
            customerEmail: $customer['email'],
            customerCpf: $customer['cpf'],
            products: $products,
            notes: self::NOTES[array_rand(self::NOTES)],
            source: PurchaseSource::Seed,
        );
    }

    /**
     * @return list<array{name: string, email: string, cpf: string}>
     */
    private function customers(int $amount): array
    {
        $customers = [];

        for ($i = 0; $i < $amount; ++$i) {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];

            $customers[] = [
                'name' => "{$first} {$last}",
                'email' => sprintf('%s.%s%d@example.com', $this->slug($first), $this->slug($last), $i + 1),
                'cpf' => $this->randomCpf(),
            ];
        }

        return $customers;
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return strtolower(preg_replace('/[^a-z]/i', '', $ascii === false ? $value : $ascii) ?? '');
    }

    private function randomCpf(): string
    {
        $digits = [];
        for ($i = 0; $i < 9; ++$i) {
            $digits[] = random_int(0, 9);
        }

        for ($position = 9; $position < 11; ++$position) {
            $sum = 0;
            for ($i = 0; $i < $position; ++$i) {
                $sum += $digits[$i] * (($position + 1) - $i);
            }
            $digits[] = ((10 * $sum) % 11) % 10;
        }

        return implode('', $digits);
    }
}
