<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Purchase\Domain\Exception\InvalidPurchase;
use App\Purchase\Domain\Model\Cpf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CpfTest extends TestCase
{
    public function testAcceptsCpfWithOrWithoutMask(): void
    {
        self::assertSame('52998224725', Cpf::fromString('529.982.247-25')->number());
        self::assertSame('52998224725', Cpf::fromString('52998224725')->number());
    }

    public function testFormatsCpf(): void
    {
        self::assertSame('529.982.247-25', Cpf::fromString('52998224725')->formatted());
    }

    #[DataProvider('invalidCpfs')]
    public function testRejectsInvalidCpf(string $cpf): void
    {
        $this->expectException(InvalidPurchase::class);

        Cpf::fromString($cpf);
    }

    public static function invalidCpfs(): iterable
    {
        yield 'dígito verificador errado' => ['529.982.247-26'];
        yield 'todos os dígitos iguais' => ['111.111.111-11'];
        yield 'curto' => ['1234567890'];
        yield 'vazio' => [''];
    }
}
