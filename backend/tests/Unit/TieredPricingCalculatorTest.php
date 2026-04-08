<?php

namespace Tests\Unit;

use App\Modules\Billing\Services\TieredPricingCalculator;
use PHPUnit\Framework\TestCase;

class TieredPricingCalculatorTest extends TestCase
{
    public function test_simple_pricing_without_tiers(): void
    {
        $result = TieredPricingCalculator::calculate('1000', null, '0.10');
        
        $this->assertEquals('100.00', $result['total']);
        $this->assertCount(1, $result['breakdown']);
        $this->assertEquals('1000', $result['breakdown'][0]['quantity']);
    }

    public function test_volume_pricing_first_tier(): void
    {
        $config = [
            'type' => 'volume',
            'tiers' => [
                ['up_to' => 1000, 'unit_price' => '0.10'],
                ['up_to' => 10000, 'unit_price' => '0.08'],
                ['up_to' => null, 'unit_price' => '0.05'],
            ],
        ];

        // 500 次调用，落在第一阶梯，单价 0.10
        $result = TieredPricingCalculator::calculate('500', $config, '0.10');
        
        $this->assertEquals('50.00', $result['total']);
        $this->assertCount(1, $result['breakdown']);
        $this->assertEquals('0.10', $result['breakdown'][0]['unit_price']);
    }

    public function test_volume_pricing_second_tier(): void
    {
        $config = [
            'type' => 'volume',
            'tiers' => [
                ['up_to' => 1000, 'unit_price' => '0.10'],
                ['up_to' => 10000, 'unit_price' => '0.08'],
                ['up_to' => null, 'unit_price' => '0.05'],
            ],
        ];

        // 5000 次调用，落在第二阶梯，所有数量使用单价 0.08
        $result = TieredPricingCalculator::calculate('5000', $config, '0.10');
        
        $this->assertEquals('400.00', $result['total']); // 5000 * 0.08
        $this->assertEquals('0.08', $result['breakdown'][0]['unit_price']);
    }

    public function test_graduated_pricing_single_tier(): void
    {
        $config = [
            'type' => 'graduated',
            'tiers' => [
                ['up_to' => 1000, 'unit_price' => '0.10'],
                ['up_to' => 10000, 'unit_price' => '0.08'],
                ['up_to' => null, 'unit_price' => '0.05'],
            ],
        ];

        // 500 次调用，只用第一阶梯
        $result = TieredPricingCalculator::calculate('500', $config, '0.10');
        
        $this->assertEquals('50.00', $result['total']);
        $this->assertCount(1, $result['breakdown']);
    }

    public function test_graduated_pricing_multiple_tiers(): void
    {
        $config = [
            'type' => 'graduated',
            'tiers' => [
                ['up_to' => 1000, 'unit_price' => '0.10'],
                ['up_to' => 10000, 'unit_price' => '0.08'],
                ['up_to' => null, 'unit_price' => '0.05'],
            ],
        ];

        // 5000 次调用：
        // 前 1000 次: 1000 * 0.10 = 100
        // 后 4000 次: 4000 * 0.08 = 320
        // 总计: 420
        $result = TieredPricingCalculator::calculate('5000', $config, '0.10');
        
        $this->assertEquals('420.00', $result['total']);
        $this->assertCount(2, $result['breakdown']);
        
        // 第一阶梯
        $this->assertEquals(1000, $result['breakdown'][0]['quantity']);
        $this->assertEquals('0.10', $result['breakdown'][0]['unit_price']);
        $this->assertEquals(100.00, $result['breakdown'][0]['amount']);
        
        // 第二阶梯
        $this->assertEquals(4000, $result['breakdown'][1]['quantity']);
        $this->assertEquals('0.08', $result['breakdown'][1]['unit_price']);
        $this->assertEquals(320.00, $result['breakdown'][1]['amount']);
    }

    public function test_graduated_pricing_all_tiers(): void
    {
        $config = [
            'type' => 'graduated',
            'tiers' => [
                ['up_to' => 1000, 'unit_price' => '0.10'],
                ['up_to' => 10000, 'unit_price' => '0.08'],
                ['up_to' => null, 'unit_price' => '0.05'],
            ],
        ];

        // 15000 次调用：
        // 前 1000 次: 1000 * 0.10 = 100
        // 1001-10000: 9000 * 0.08 = 720
        // 10001-15000: 5000 * 0.05 = 250
        // 总计: 1070
        $result = TieredPricingCalculator::calculate('15000', $config, '0.10');
        
        $this->assertEquals('1070.00', $result['total']);
        $this->assertCount(3, $result['breakdown']);
    }

    public function test_validate_config_valid(): void
    {
        $config = [
            'type' => 'graduated',
            'tiers' => [
                ['up_to' => 1000, 'unit_price' => '0.10'],
                ['up_to' => null, 'unit_price' => '0.05'],
            ],
        ];

        $this->assertTrue(TieredPricingCalculator::validateConfig($config));
    }

    public function test_validate_config_invalid_order(): void
    {
        $config = [
            'tiers' => [
                ['up_to' => 10000, 'unit_price' => '0.10'],
                ['up_to' => 1000, 'unit_price' => '0.05'], // 错误：应该递增
            ],
        ];

        $this->assertFalse(TieredPricingCalculator::validateConfig($config));
    }

    public function test_validate_config_null_not_last(): void
    {
        $config = [
            'tiers' => [
                ['up_to' => null, 'unit_price' => '0.10'], // 错误：null 应该在最后
                ['up_to' => 1000, 'unit_price' => '0.05'],
            ],
        ];

        $this->assertFalse(TieredPricingCalculator::validateConfig($config));
    }
}
