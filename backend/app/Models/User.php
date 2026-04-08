<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['role' => $this->role];
    }

    // 关联关系
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function activeSubscription() { return $this->hasOne(Subscription::class)->where('status', 'active'); }
    public function usageRecords() { return $this->hasMany(UsageRecord::class); }
    public function bills() { return $this->hasMany(Bill::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function operationLogs() { return $this->hasMany(OperationLog::class); }
    public function refunds() { return $this->hasMany(Refund::class); }

    // RBAC 关联
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * 检查用户是否拥有指定权限（通过RBAC角色）
     */
    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($q) use ($permission) {
            $q->where('name', $permission);
        })->exists();
    }

    /**
     * 检查用户是否拥有指定RBAC角色
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * 获取用户所有权限（合并所有角色的权限）
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        return Permission::whereHas('roles', function ($q) {
            $q->whereIn('roles.id', $this->roles()->pluck('roles.id'));
        })->pluck('name');
    }

    // 向后兼容的简单角色检查
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->hasRole('admin');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
