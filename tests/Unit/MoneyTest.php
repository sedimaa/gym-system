<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_amounts_as_philippine_peso(): void
    {
        $this->assertSame('₱1,234.00', Money::peso(1234));
        $this->assertSame('₱1,234.50', Money::peso('1234.50'));
        $this->assertSame('₱0.00', Money::peso(null));
        $this->assertSame('-₱150.00', Money::peso(-150));
    }
}
