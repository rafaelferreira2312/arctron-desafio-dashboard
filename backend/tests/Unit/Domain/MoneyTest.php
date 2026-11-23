<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Purchase\Domain\Exception\InvalidPurchase;
use App\Purchase\Domain\Model\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('validValues')]
    public function testConvertsDecimalToCents(string|float|int $value, int $expectedCents): void
    {
        self::assertSame($expectedCents, Money::fromDecimal($value)->cents());
    }

    public static function validValues(): iterable
    {
        yield 'string com centavos' => ['150.90', 15090];
        yield 'string com uma casa' => ['150.9', 15090];
        yield 'string inteira' => ['150', 15000];
        yield 'float' => [150.9, 15090];
        yield 'float com erro de ponto flutuante' => [0.1 + 0.2, 30];
        yield 'int' => [42, 4200];
        yield 'zero' => ['0', 0];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(string|float $value): void
    {
        $this->expectException(InvalidPurchase::class);

        Money::fromDecimal($value);
    }

    public static function invalidValues(): iterable
    {
        yield 'negativo' => ['-10.00'];
        yield 'float negativo' => [-1.5];
        yield 'três casas decimais' => ['10.999'];
        yield 'vírgula' => ['10,50'];
        yield 'texto' => ['dez reais'];
        yield 'vazio' => [''];
    }

    public function testFormatsCentsAsDecimal(): void
    {
        self::assertSame('1234.05', Money::fromCents(123405)->toDecimal());
        self::assertSame('0.00', Money::zero()->toDecimal());
    }

    public function testArithmetic(): void
    {
        $price = Money::fromDecimal('19.90');

        self::assertSame(5970, $price->multiply(3)->cents());
        self::assertSame(3980, $price->add($price)->cents());
        self::assertTrue($price->multiply(2)->isGreaterThan($price));
    }
}
