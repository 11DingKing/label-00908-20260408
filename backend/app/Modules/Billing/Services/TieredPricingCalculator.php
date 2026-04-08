<?php

namespace App\Modules\Billing\Services;

/**
 * 阶梯计费计算器
 * 
 * 支持多种阶梯计费模式：
 * - volume: 总量阶梯（根据总量确定单价，所有数量使用同一单价）
 * - graduated: 累进阶梯（不同区间使用不同单价，分段计费）
 * 
 * 阶梯配置格式：
 * [
 *     'type' => 'graduated', // 或 'volume'
 *     'tiers' => [
 *         ['up_to' => 1000, 'unit_price' => '0.10'],      // 0-1000: 0.10/次
 *         ['up_to' => 10000, 'unit_price' => '0.08'],     // 1001-10000: 0.08/次
 *         ['up_to' => null, 'unit_price' => '0.05'],      // 10001+: 0.05/次
 *     ]
 * ]
 */
class TieredPricingCalculator
{
    /**
     * 计算阶梯价格
     * 
     * @param string $quantity 使用量
     * @param array $tieredConfig 阶梯配置
     * @param string $defaultUnitPrice 默认单价（无阶梯配置时使用）
     * @return array ['total' => string, 'breakdown' => array]
     */
    public static function calculate(string $quantity, ?array $tieredConfig, string $defaultUnitPrice): array
    {
        // 无阶梯配置，使用简单计算
        if (empty($tieredConfig) || empty($tieredConfig['tiers'])) {
            $total = MoneyCalculator::mul($quantity, $defaultUnitPrice);
            return [
                'total' => MoneyCalculator::round($total),
                'breakdown' => [[
                    'from' => 0,
                    'to' => $quantity,
                    'quantity' => $quantity,
                    'unit_price' => $defaultUnitPrice,
                    'amount' => MoneyCalculator::round($total),
                ]],
            ];
        }

        $type = $tieredConfig['type'] ?? 'graduated';
        $tiers = $tieredConfig['tiers'];

        return match ($type) {
            'volume' => self::calculateVolume($quantity, $tiers),
            'graduated' => self::calculateGraduated($quantity, $tiers),
            default => self::calculateGraduated($quantity, $tiers),
        };
    }

    /**
     * 总量阶梯计费
     * 根据总量确定适用的单价，所有数量使用同一单价
     */
    protected static function calculateVolume(string $quantity, array $tiers): array
    {
        $unitPrice = '0';
        
        foreach ($tiers as $tier) {
            $upTo = $tier['up_to'];
            $unitPrice = (string) $tier['unit_price'];
            
            // 找到适用的阶梯
            if ($upTo === null || MoneyCalculator::compare($quantity, (string) $upTo) <= 0) {
                break;
            }
        }

        $total = MoneyCalculator::round(MoneyCalculator::mul($quantity, $unitPrice));

        return [
            'total' => $total,
            'breakdown' => [[
                'from' => 0,
                'to' => $quantity,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => $total,
            ]],
        ];
    }

    /**
     * 累进阶梯计费
     * 不同区间使用不同单价，分段计费后累加
     */
    protected static function calculateGraduated(string $quantity, array $tiers): array
    {
        $remaining = $quantity;
        $total = '0';
        $breakdown = [];
        $previousUpTo = '0';

        foreach ($tiers as $tier) {
            if (MoneyCalculator::compare($remaining, '0') <= 0) {
                break;
            }

            $upTo = $tier['up_to'];
            $unitPrice = (string) $tier['unit_price'];

            // 计算当前阶梯的数量上限
            if ($upTo === null) {
                $tierQuantity = $remaining;
            } else {
                $tierCapacity = MoneyCalculator::sub((string) $upTo, $previousUpTo);
                $tierQuantity = MoneyCalculator::compare($remaining, $tierCapacity) <= 0 
                    ? $remaining 
                    : $tierCapacity;
            }

            // 计算当前阶梯费用
            $tierAmount = MoneyCalculator::round(MoneyCalculator::mul($tierQuantity, $unitPrice));
            $total = MoneyCalculator::add($total, $tierAmount);

            $breakdown[] = [
                'from' => MoneyCalculator::toFloat($previousUpTo, 0),
                'to' => $upTo ?? '∞',
                'quantity' => MoneyCalculator::toFloat($tierQuantity, 4),
                'unit_price' => $unitPrice,
                'amount' => MoneyCalculator::toFloat($tierAmount),
            ];

            $remaining = MoneyCalculator::sub($remaining, $tierQuantity);
            $previousUpTo = $upTo !== null ? (string) $upTo : $previousUpTo;
        }

        return [
            'total' => MoneyCalculator::round($total),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * 验证阶梯配置格式
     */
    public static function validateConfig(array $config): bool
    {
        if (!isset($config['tiers']) || !is_array($config['tiers'])) {
            return false;
        }

        $previousUpTo = 0;
        foreach ($config['tiers'] as $index => $tier) {
            if (!isset($tier['unit_price'])) {
                return false;
            }

            $upTo = $tier['up_to'] ?? null;
            
            // 最后一个阶梯可以是 null（无上限）
            if ($upTo !== null) {
                if (!is_numeric($upTo) || $upTo <= $previousUpTo) {
                    return false;
                }
                $previousUpTo = $upTo;
            } elseif ($index !== count($config['tiers']) - 1) {
                // null 只能出现在最后一个阶梯
                return false;
            }
        }

        return true;
    }
}
