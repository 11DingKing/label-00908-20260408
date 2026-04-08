<?php

namespace App\Traits;

use App\Models\OperationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logOperation($model, 'created');
        });

        static::updated(function ($model) {
            static::logOperation($model, 'updated', $model->getChanges());
        });

        static::deleted(function ($model) {
            static::logOperation($model, 'deleted');
        });
    }

    protected static function logOperation($model, string $action, array $changes = []): void
    {
        // 避免记录 OperationLog 自身的操作
        if ($model instanceof OperationLog) {
            return;
        }

        try {
            OperationLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'description' => static::getAuditDescription($model, $action),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'request_data' => !empty($changes) ? $changes : $model->toArray(),
            ]);
        } catch (\Exception $e) {
            // 静默失败，不影响主业务
            \Log::warning('审计日志记录失败: ' . $e->getMessage());
        }
    }

    protected static function getAuditDescription($model, string $action): string
    {
        $modelName = class_basename($model);
        $actions = [
            'created' => '创建',
            'updated' => '更新',
            'deleted' => '删除',
        ];

        return ($actions[$action] ?? $action) . " {$modelName} #{$model->getKey()}";
    }
}
