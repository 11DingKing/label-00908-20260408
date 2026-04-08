<?php

namespace Tests\Unit\Models;

use App\Models\MeteringDimension;
use App\Models\UsageRecord;
use App\Models\User;
use App\Modules\Subscription\Models\UsageRecord as ModuleUsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeteringDimensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dimension_has_usage_records_relationship(): void
    {
        $dimension = MeteringDimension::factory()->create();
        $user = User::factory()->create();
        UsageRecord::factory()->count(3)->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
        ]);

        $this->assertCount(3, $dimension->usageRecords);
        $this->assertInstanceOf(ModuleUsageRecord::class, $dimension->usageRecords->first());
    }

    public function test_dimension_active_scope(): void
    {
        MeteringDimension::factory()->count(3)->create(['is_active' => true]);
        MeteringDimension::factory()->count(2)->create(['is_active' => false]);

        $activeDimensions = MeteringDimension::active()->get();

        $this->assertCount(3, $activeDimensions);
    }

    public function test_dimension_unit_price_cast_to_decimal(): void
    {
        $dimension = MeteringDimension::factory()->create(['unit_price' => 0.0123]);

        $this->assertEquals('0.0123', $dimension->unit_price);
    }

    public function test_dimension_code_is_unique(): void
    {
        MeteringDimension::factory()->create(['code' => 'api_calls']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        MeteringDimension::factory()->create(['code' => 'api_calls']);
    }

    public function test_dimension_attributes(): void
    {
        $dimension = MeteringDimension::factory()->create([
            'code' => 'storage',
            'name' => '存储空间',
            'description' => '文件存储空间',
            'unit' => 'GB',
            'unit_price' => 0.5,
            'is_active' => true,
        ]);

        $this->assertEquals('storage', $dimension->code);
        $this->assertEquals('存储空间', $dimension->name);
        $this->assertEquals('文件存储空间', $dimension->description);
        $this->assertEquals('GB', $dimension->unit);
        $this->assertEquals('0.5000', $dimension->unit_price);
        $this->assertTrue($dimension->is_active);
    }
}
