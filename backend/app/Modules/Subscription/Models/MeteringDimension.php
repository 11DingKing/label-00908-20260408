<?php

namespace App\Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\MeteringDimensionFactory;

class MeteringDimension extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return MeteringDimensionFactory::new();
    }

    protected $fillable = ['code', 'name', 'description', 'unit', 'unit_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function usageRecords() { return $this->hasMany(UsageRecord::class, 'dimension_id'); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
