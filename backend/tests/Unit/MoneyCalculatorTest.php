<?php

namespace Tests\Unit;

use App\Modules\Billing\Services\MoneyCalculator;
use PHPUnit\Framework\TestCase;

class MoneyCalculatorTest extends TestCase
{
    public function test_add_returns_correct_result(): void
    {
        // 经典浮点数精度问题：0.1 + 0.2 != 0.3
        $result = MoneyCalculator::add('0.1', '0.2');
        $this->assertEquals('0.3000000000', $result);
        $this->assertEquals(0.30, MoneyCalculator::toFloat($result));
    }

    public function test_sub_returns_correct_result(): void
    {
        $result = MoneyCalculator::sub('100.50', '30.25');
        $this->assertEquals(70.25, MoneyCalculator::toFloat($result));
    }

    public function test_mul_returns_correct_result(): void
    {
        // 1000 次 API 调用 * 0.001 单价
        $result = MoneyCalculator::mul('1000', '0.001');
        $this->assertEquals(1.00, MoneyCalculator::toFloat($result));
    }

    public function test_div_returns_correct_result(): void
    {
        $result = MoneyCalculator::div('100', '3');
        $this->assertEquals(33.33, MoneyCalculator::toFloat($result));
    }

    public function test_div_by_zero_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MoneyCalculator::div('100', '0');
    }

    public function test_round_works_correctly(): void
    {
        $this->assertEquals('10.56', MoneyCalculator::round('10.555'));
        $this->assertEquals('10.55', MoneyCalculator::round('10.554'));
        $this->assertEquals('10.00', MoneyCalculator::round('10.001'));
    }

    public function test_compare_returns_correct_values(): void
    {
        $this->assertEquals(1, MoneyCalculator::compare('100', '99.99'));
        $this->assertEquals(0, MoneyCalculator::compare('100.00', '100'));
        $this->assertEquals(-1, MoneyCalculator::compare('99.99', '100'));
    }

    public function test_max_returns_larger_value(): void
    {
        $result = MoneyCalculator::max('100', '99.99');
        $this->assertEquals(100.00, MoneyCalculator::toFloat($result));
        
        $result = MoneyCalculator::max('-10', '0');
        $this->assertEquals(0.00, MoneyCalculator::toFloat($result));
    }

    public function test_is_positive_negative_zero(): void
    {
        $this->assertTrue(MoneyCalculator::isPositive('100'));
        $this->assertFalse(MoneyCalculator::isPositive('0'));
        $this->assertFalse(MoneyCalculator::isPositive('-100'));
        
        $this->assertTrue(MoneyCalculator::isNegative('-100'));
        $this->assertFalse(MoneyCalculator::isNegative('0'));
        
        $this->assertTrue(MoneyCalculator::isZero('0'));
        $this->assertTrue(MoneyCalculator::isZero('0.00'));
    }

    public function test_handles_float_input(): void
    {
        // 测试浮点数输入也能正确处理
        $result = MoneyCalculator::add(0.1, 0.2);
        $this->assertEquals(0.30, MoneyCalculator::toFloat($result));
    }

    public function test_complex_billing_calculation(): void
    {
        // 模拟复杂计费场景
        $subscriptionFee = '99.00';
        $usageQuantity = '1523';
        $unitPrice = '0.0085';
        
        $usageFee = MoneyCalculator::mul($usageQuantity, $unitPrice);
        $subtotal = MoneyCalculator::add($subscriptionFee, $usageFee);
        $taxRate = '0.13';
        $tax = MoneyCalculator::mul($subtotal, $taxRate);
        $total = MoneyCalculator::add($subtotal, $tax);
        
        // 验证精确计算
        $this->assertEquals(12.95, MoneyCalculator::toFloat($usageFee)); // 1523 * 0.0085 = 12.9455
        $this->assertEquals(111.95, MoneyCalculator::toFloat($subtotal));
        $this->assertEquals(14.55, MoneyCalculator::toFloat($tax)); // 111.95 * 0.13 = 14.5535
        $this->assertEquals(126.50, MoneyCalculator::toFloat($total));
    }
}
