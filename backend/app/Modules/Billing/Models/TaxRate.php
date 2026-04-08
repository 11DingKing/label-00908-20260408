<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'rate', 'region', 'is_inclusive', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function calculateTax(float $amount): float
    {
        if ($this->is_inclusive) {
            return round($amount - ($amount / (1 + $this->rate)), 2);
        }
        return round($amount * $this->rate, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRegion($query, string $region)
    {
        return $query->where('region', $region);
    }
}
