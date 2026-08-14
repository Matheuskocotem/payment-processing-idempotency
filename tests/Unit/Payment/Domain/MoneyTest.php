<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Domain;

use App\Payment\Domain\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_holds_amount_in_integer_cents(): void
    {
        $money = new Money(10_050, 'BRL');

        $this->assertSame(10_050, $money->amountCents);
        $this->assertSame('BRL', $money->currency);
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(-1, 'BRL');
    }

    public function test_it_rejects_currency_codes_that_are_not_three_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(100, 'R$');
    }

    public function test_equals_compares_amount_and_currency(): void
    {
        $a = new Money(100, 'BRL');
        $b = new Money(100, 'BRL');
        $c = new Money(100, 'USD');
        $d = new Money(200, 'BRL');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
        $this->assertFalse($a->equals($d));
    }
}
