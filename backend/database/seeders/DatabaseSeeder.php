<?php

namespace Database\Seeders;

use App\Models\MeteringDimension;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 创建管理员用户 (与README文档一致)
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理员',
                'password' => Hash::make('password'),
                'phone' => '13800138000',
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 创建测试用户
        User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => '测试用户',
                'password' => Hash::make('password'),
                'phone' => '13900139000',
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 创建订阅计划
        $plans = [
            [
                'name' => '免费版',
                'code' => 'free',
                'description' => '适合个人用户体验',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'features' => ['基础功能', '社区支持', '5GB存储'],
                'included_usage' => ['api_calls' => 1000, 'storage_gb' => 5, 'bandwidth_gb' => 10],
                'sort_order' => 1,
            ],
            [
                'name' => '基础版',
                'code' => 'basic',
                'description' => '适合小型团队',
                'price' => 99,
                'billing_cycle' => 'monthly',
                'features' => ['全部基础功能', '邮件支持', '50GB存储', 'API访问'],
                'included_usage' => ['api_calls' => 10000, 'storage_gb' => 50, 'bandwidth_gb' => 100],
                'sort_order' => 2,
            ],
            [
                'name' => '专业版',
                'code' => 'professional',
                'description' => '适合中型企业',
                'price' => 299,
                'billing_cycle' => 'monthly',
                'features' => ['全部基础功能', '优先支持', '200GB存储', '高级API', '数据分析'],
                'included_usage' => ['api_calls' => 100000, 'storage_gb' => 200, 'bandwidth_gb' => 500],
                'sort_order' => 3,
            ],
            [
                'name' => '企业版',
                'code' => 'enterprise',
                'description' => '适合大型企业',
                'price' => 999,
                'billing_cycle' => 'monthly',
                'features' => ['全部功能', '专属客服', '无限存储', '定制开发', 'SLA保障'],
                'included_usage' => ['api_calls' => 1000000, 'storage_gb' => 1000, 'bandwidth_gb' => 2000],
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::firstOrCreate(['code' => $plan['code']], $plan);
        }

        // 创建计量维度
        $dimensions = [
            [
                'code' => 'api_calls',
                'name' => 'API调用次数',
                'description' => '每次API请求计为一次调用',
                'unit' => '次',
                'unit_price' => 0.001,
            ],
            [
                'code' => 'storage_gb',
                'name' => '存储空间',
                'description' => '数据存储使用量',
                'unit' => 'GB',
                'unit_price' => 0.5,
            ],
            [
                'code' => 'bandwidth_gb',
                'name' => '带宽流量',
                'description' => '数据传输流量',
                'unit' => 'GB',
                'unit_price' => 0.2,
            ],
            [
                'code' => 'compute_hours',
                'name' => '计算时长',
                'description' => '服务器计算资源使用时长',
                'unit' => '小时',
                'unit_price' => 0.1,
            ],
            [
                'code' => 'messages',
                'name' => '消息数量',
                'description' => '发送的消息数量',
                'unit' => '条',
                'unit_price' => 0.01,
            ],
        ];

        foreach ($dimensions as $dimension) {
            MeteringDimension::firstOrCreate(['code' => $dimension['code']], $dimension);
        }
    }
}
