<?php

namespace App\Traits;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HasDatabaseTransaction
{
    /**
     * 在数据库事务中执行操作，支持重试
     */
    protected function executeInTransaction(Closure $callback, int $maxAttempts = 3): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                return DB::transaction($callback);
            } catch (Throwable $e) {
                $lastException = $e;

                // 检查是否是死锁或连接丢失错误
                if ($this->isRetryableException($e) && $attempts < $maxAttempts) {
                    Log::warning("数据库操作失败，正在重试 ({$attempts}/{$maxAttempts})", [
                        'error' => $e->getMessage(),
                    ]);
                    usleep(100000 * $attempts); // 递增延迟
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException;
    }

    /**
     * 检查异常是否可重试
     */
    private function isRetryableException(Throwable $e): bool
    {
        $retryableMessages = [
            'Deadlock found',
            'Lock wait timeout',
            'Connection lost',
            'server has gone away',
            'no connection to the server',
            'Lost connection',
        ];

        foreach ($retryableMessages as $message) {
            if (stripos($e->getMessage(), $message) !== false) {
                return true;
            }
        }

        return false;
    }
}
