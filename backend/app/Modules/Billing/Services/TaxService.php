<?php

namespace App\Modules\Billing\Services;

use App\Models\TaxRate;

class TaxService
{
    public function calculateTax(float $amount, ?string $region = null): array
    {
        if (!config('payment.tax.enabled', true)) {
            return ['tax_amount' => 0, 'tax_rate' => 0, 'tax_name' => null];
        }
        $region = $region ?? config('payment.tax.default_region', 'CN');
        $taxRate = TaxRate::active()->forRegion($region)->first();
        if (!$taxRate) {
            return ['tax_amount' => 0, 'tax_rate' => 0, 'tax_name' => null];
        }
        return [
            'tax_amount' => $taxRate->calculateTax($amount),
            'tax_rate' => (float) $taxRate->rate,
            'tax_name' => $taxRate->name,
            'is_inclusive' => $taxRate->is_inclusive,
        ];
    }
}
