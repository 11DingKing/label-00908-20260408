<?php

namespace App\Modules\Billing\Services;

/**
 * 货币精确计算器
 * 
 * 使用 BCMath 扩展进行高精度货币计算，避免浮点数精度问题
 */
class MoneyCalculator
{
    /** 内部计算精度 */
    protected const SCALE = 10;
    
    /** 输出精度（保留2位小数） */
    protected const OUTPUT_SCALE = 2;

    /**
     * 加法
     */
    public static function add(string|float|int $a, string|float|int $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /**
     * 减法
     */
    public static function sub(string|float|int $a, string|float|int $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /**
     * 乘法
     */
    public static function mul(string|float|int $a, string|float|int $b): string
    {
        return bcmul(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /**
     * 除法
     */
    public static function div(string|float|int $a, string|float|int $b): string
    {
        $divisor = self::normalize($b);
        if (bccomp($divisor, '0', self::SCALE) === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return bcdiv(self::normalize($a), $divisor, self::SCALE);
    }

    /**
     * 比较：返回 -1, 0, 1
     */
    public static function compare(string|float|int $a, string|float|int $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /**
     * 取最大值
     */
    public static function max(string|float|int $a, string|float|int $b): string
    {
        return self::compare($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    /**
     * 四舍五入到指定精度
     */
    public static function round(string|float|int $value, int $scale = self::OUTPUT_SCALE): string
    {
        $normalized = self::normalize($value);
        
        // 使用 bcadd 实现四舍五入
        $sign = bccomp($normalized, '0', self::SCALE) >= 0 ? '1' : '-1';
        $half = bcmul($sign, bcpow('0.1', (string)($scale + 1), self::SCALE), self::SCALE);
        $half = bcmul($half, '5', self::SCALE);
        
        $result = bcadd($normalized, $half, self::SCALE);
        
        // 截断到目标精度
        return self::truncate($result, $scale);
    }

    /**
     * 截断到指定精度（不四舍五入）
     */
    public static function truncate(string|float|int $value, int $scale = self::OUTPUT_SCALE): string
    {
        $normalized = self::normalize($value);
        
        if ($scale === 0) {
            $pos = strpos($normalized, '.');
            return $pos === false ? $normalized : substr($normalized, 0, $pos);
        }
        
        $pos = strpos($normalized, '.');
        if ($pos === false) {
            return $normalized . '.' . str_repeat('0', $scale);
        }
        
        $decimals = substr($normalized, $pos + 1);
        if (strlen($decimals) >= $scale) {
            return substr($normalized, 0, $pos + 1 + $scale);
        }
        
        return $normalized . str_repeat('0', $scale - strlen($decimals));
    }

    /**
     * 转换为浮点数（仅用于最终输出）
     */
    public static function toFloat(string|float|int $value, int $scale = self::OUTPUT_SCALE): float
    {
        return (float) self::round($value, $scale);
    }

    /**
     * 标准化输入值为字符串
     */
    protected static function normalize(string|float|int $value): string
    {
        if (is_float($value)) {
            // 使用 sprintf 避免科学计数法
            return sprintf('%.10f', $value);
        }
        return (string) $value;
    }

    /**
     * 判断是否为零
     */
    public static function isZero(string|float|int $value): bool
    {
        return self::compare($value, '0') === 0;
    }

    /**
     * 判断是否为正数
     */
    public static function isPositive(string|float|int $value): bool
    {
        return self::compare($value, '0') > 0;
    }

    /**
     * 判断是否为负数
     */
    public static function isNegative(string|float|int $value): bool
    {
        return self::compare($value, '0') < 0;
    }
}
