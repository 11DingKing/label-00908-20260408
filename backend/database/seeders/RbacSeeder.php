<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // 创建权限
        $permissions = [
            // 订阅管理
            ['name' => 'subscriptions.view', 'display_name' => '查看订阅', 'group' => 'subscriptions'],
            ['name' => 'subscriptions.manage', 'display_name' => '管理订阅计划', 'group' => 'subscriptions'],
            // 账单
            ['name' => 'bills.view', 'display_name' => '查看账单', 'group' => 'bills'],
            ['name' => 'bills.manage', 'display_name' => '管理账单', 'group' => 'bills'],
            // 支付
            ['name' => 'payments.view', 'display_name' => '查看支付', 'group' => 'payments'],
            ['name' => 'payments.refund', 'display_name' => '处理退款', 'group' => 'payments'],
            // 报表
            ['name' => 'reports.view', 'display_name' => '查看报表', 'group' => 'reports'],
            ['name' => 'reports.export', 'display_name' => '导出报表', 'group' => 'reports'],
            // 用户
            ['name' => 'users.view', 'display_name' => '查看用户', 'group' => 'users'],
            ['name' => 'users.manage', 'display_name' => '管理用户', 'group' => 'users'],
            // 系统
            ['name' => 'system.config', 'display_name' => '系统配置', 'group' => 'system'],
            ['name' => 'coupons.manage', 'display_name' => '管理优惠券', 'group' => 'system'],
            ['name' => 'tax.manage', 'display_name' => '管理税率', 'group' => 'system'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // 创建角色
        $admin = Role::firstOrCreate(['name' => 'admin'], [
            'display_name' => '系统管理员',
            'description' => '拥有所有权限',
        ]);
        $admin->permissions()->sync(Permission::pluck('id'));

        $finance = Role::firstOrCreate(['name' => 'finance'], [
            'display_name' => '财务人员',
            'description' => '查看报表、处理退款',
        ]);
        $finance->permissions()->sync(
            Permission::whereIn('name', [
                'bills.view', 'bills.manage', 'payments.view', 'payments.refund',
                'reports.view', 'reports.export',
            ])->pluck('id')
        );

        $operator = Role::firstOrCreate(['name' => 'operator'], [
            'display_name' => '运营人员',
            'description' => '管理订阅计划和优惠券',
        ]);
        $operator->permissions()->sync(
            Permission::whereIn('name', [
                'subscriptions.view', 'subscriptions.manage',
                'coupons.manage', 'users.view',
            ])->pluck('id')
        );

        $support = Role::firstOrCreate(['name' => 'support'], [
            'display_name' => '客服人员',
            'description' => '查询账单和用户信息',
        ]);
        $support->permissions()->sync(
            Permission::whereIn('name', [
                'subscriptions.view', 'bills.view', 'payments.view', 'users.view',
            ])->pluck('id')
        );
    }
}
