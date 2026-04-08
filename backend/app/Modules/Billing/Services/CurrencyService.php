<?php

namespace App\Modules\Billing\Services;

class CurrencyService
{
    protected string $baseCurrency;

    public function __construct()
    {
        $this->baseCurrency = config('payment.base_currency', 'CNY');
    }

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }

    public function convertToBase(float $amount, string $fromCurrency): float
    {
        if ($fromCurrency === $this->baseCurrency) return $amount;
        $rate = $this->getExchangeRate($fromCurrency);
        return round($amount * $rate, 2);
    }

    public function convertFromBase(float $amount, string $toCurrency): float
    {
        if ($toCurrency === $this->baseCurrency) return $amount;
        $rate = $this->getExchangeRate($toCurrency);
        return round($amount / $rate, 2);
    }

    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) return $amount;
        $baseAmount = $this->convertToBase($amount, $from);
        return $this->convertFromBase($baseAmount, $to);
    }

    public function getExchangeRate(string $currency): float
    {
        $rates = config('payment.exchange_rates', []);
        return $rates[$currency] ?? throw new \InvalidArgumentException("不支持的币种: {$currency}");
    }

    public function getSupportedCurrencies(): array
    {
        return config('payment.supported_currencies', [$this->baseCurrency]);
    }
}
