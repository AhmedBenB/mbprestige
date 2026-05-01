<?php

namespace Tests\Unit;

use App\Services\Payment\DepositCalculator;
use Tests\TestCase;

class DepositCalculatorTest extends TestCase
{
    public function test_deposit_is_ten_percent_by_default(): void
    {
        config()->set('payments.deposit_percentage', 10);

        $summary = app(DepositCalculator::class)->summaryFromAmount(10000);

        $this->assertSame(10250.0, $summary['total']);
        $this->assertSame(1025.0, $summary['deposit_now']);
        $this->assertSame(9225.0, $summary['remaining_after_deposit']);
    }
}
