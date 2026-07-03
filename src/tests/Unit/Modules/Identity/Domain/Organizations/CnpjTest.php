<?php

namespace Tests\Unit\Modules\Identity\Domain\Organizations;

use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    public function test_it_normalizes_a_valid_cnpj(): void
    {
        $cnpj = new Cnpj('11.222.333/0001-81');

        $this->assertSame('11222333000181', $cnpj->value());
        $this->assertSame('11.222.333/0001-81', $cnpj->formatted());
        $this->assertSame('11222333', $cnpj->root());
        $this->assertSame('0001', $cnpj->branch());
        $this->assertSame('81', $cnpj->checkDigits());
        $this->assertSame('11222333000181', (string) $cnpj);
    }

    public function test_it_accepts_an_unformatted_valid_cnpj(): void
    {
        $cnpj = new Cnpj('11222333000181');

        $this->assertSame('11222333000181', $cnpj->value());
    }

    public function test_it_compares_cnpjs(): void
    {
        $cnpj = new Cnpj('11.222.333/0001-81');

        $this->assertTrue($cnpj->equals(new Cnpj('11222333000181')));
    }

    public function test_it_creates_from_nullable_value(): void
    {
        $this->assertNull(Cnpj::fromNullable(null));
        $this->assertNull(Cnpj::fromNullable(''));

        $this->assertSame('11222333000181', Cnpj::fromNullable('11.222.333/0001-81')?->value());
    }

    public function test_it_rejects_invalid_cnpj(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cnpj('11.222.333/0001-00');
    }

    public function test_it_rejects_repeated_digits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cnpj('00.000.000/0000-00');
    }
}
