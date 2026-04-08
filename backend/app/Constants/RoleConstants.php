<?php

namespace App\Constants;

/**
 * 角色常量定义
 * 
 * 统一管理系统中的角色标识，避免硬编码
 */
final class RoleConstants
{
    /** 超级管理员 */
    public const ADMIN = 'admin';
    
    /** 超级管理员（别名） */
    public const SUPER_ADMIN = 'super_admin';
    
    /** 普通用户 */
    public const USER = 'user';
    
    /** 财务人员 */
    public const FINANCE = 'finance';
    
    /** 客服人员 */
    public const SUPPORT = 'support';

    /**
     * 获取所有超级管理员角色
     */
    public static function getSuperAdminRoles(): array
    {
        return [self::ADMIN, self::SUPER_ADMIN];
    }

    /**
     * 判断是否为超级管理员角色
     */
    public static function isSuperAdmin(string $role): bool
    {
        return in_array($role, self::getSuperAdminRoles(), true);
    }

    /**
     * 获取所有角色
     */
    public static function all(): array
    {
        return [
            self::ADMIN,
            self::SUPER_ADMIN,
            self::USER,
            self::FINANCE,
            self::SUPPORT,
        ];
    }
}
