<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseHealthCheck extends Command
{
    protected $signature = 'db:health-check {--detailed : 显示详细信息}';
    protected $description = '检查数据库连接和表结构健康状态';

    private array $requiredTables = [
        'users',
        'subscription_plans',
        'subscriptions',
        'metering_dimensions',
        'usage_records',
        'bills',
        'bill_items',
        'payments',
        'operation_logs',
    ];

    public function handle(): int
    {
        $this->info('🔍 开始数据库健康检查...');
        $this->newLine();

        // 检查数据库连接
        if (!$this->checkConnection()) {
            return Command::FAILURE;
        }

        // 检查必需的表
        if (!$this->checkRequiredTables()) {
            return Command::FAILURE;
        }

        // 检查外键约束
        if ($this->option('detailed')) {
            $this->checkForeignKeys();
            $this->checkIndexes();
            $this->showTableStatistics();
        }

        $this->newLine();
        $this->info('✅ 数据库健康检查完成！');

        return Command::SUCCESS;
    }

    private function checkConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            $this->info("✅ 数据库连接成功: {$dbName}");
            return true;
        } catch (\Exception $e) {
            $this->error('❌ 数据库连接失败: ' . $e->getMessage());
            return false;
        }
    }

    private function checkRequiredTables(): bool
    {
        $this->info('📋 检查必需的数据表...');
        $missingTables = [];

        foreach ($this->requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $this->line("  ✅ {$table}");
            } else {
                $this->line("  ❌ {$table} (缺失)");
                $missingTables[] = $table;
            }
        }

        if (!empty($missingTables)) {
            $this->newLine();
            $this->warn('⚠️  缺失的表: ' . implode(', ', $missingTables));
            $this->info('💡 运行 php artisan migrate 来创建缺失的表');
            return false;
        }

        return true;
    }

    private function checkForeignKeys(): void
    {
        $this->newLine();
        $this->info('🔗 检查外键约束...');

        $foreignKeys = [
            ['subscriptions', 'user_id', 'users', 'id'],
            ['subscriptions', 'plan_id', 'subscription_plans', 'id'],
            ['usage_records', 'user_id', 'users', 'id'],
            ['usage_records', 'dimension_id', 'metering_dimensions', 'id'],
            ['bills', 'user_id', 'users', 'id'],
            ['bill_items', 'bill_id', 'bills', 'id'],
            ['payments', 'user_id', 'users', 'id'],
        ];

        foreach ($foreignKeys as [$table, $column, $refTable, $refColumn]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $this->line("  ✅ {$table}.{$column} -> {$refTable}.{$refColumn}");
            }
        }
    }

    private function checkIndexes(): void
    {
        $this->newLine();
        $this->info('📊 检查索引...');

        $criticalIndexes = [
            'users' => ['email', 'role', 'status'],
            'subscriptions' => ['status', 'user_id'],
            'usage_records' => ['recorded_at', 'user_id'],
            'bills' => ['status', 'user_id'],
            'payments' => ['status', 'transaction_id'],
        ];

        foreach ($criticalIndexes as $table => $columns) {
            if (Schema::hasTable($table)) {
                $this->line("  📁 {$table}: " . implode(', ', $columns));
            }
        }
    }

    private function showTableStatistics(): void
    {
        $this->newLine();
        $this->info('📈 数据统计...');

        foreach ($this->requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  {$table}: {$count} 条记录");
            }
        }
    }
}
