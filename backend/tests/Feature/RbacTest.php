<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'finance', 'display_name' => '财务']);

        $user->roles()->attach($role);

        $this->assertTrue($user->hasRole('finance'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_role_can_have_permissions(): void
    {
        $role = Role::create(['name' => 'finance', 'display_name' => '财务']);
        $perm = Permission::create([
            'name' => 'reports.view', 'display_name' => '查看报表', 'group' => 'reports',
        ]);

        $role->permissions()->attach($perm);

        $this->assertTrue($role->hasPermission('reports.view'));
        $this->assertFalse($role->hasPermission('users.manage'));
    }

    public function test_user_has_permission_through_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $role = Role::create(['name' => 'finance', 'display_name' => '财务']);
        $perm = Permission::create([
            'name' => 'payments.refund', 'display_name' => '处理退款', 'group' => 'payments',
        ]);

        $role->permissions()->attach($perm);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('payments.refund'));
        $this->assertFalse($user->hasPermission('system.config'));
    }

    public function test_user_get_all_permissions(): void
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'finance', 'display_name' => '财务']);
        $role2 = Role::create(['name' => 'support', 'display_name' => '客服']);

        $perm1 = Permission::create(['name' => 'reports.view', 'display_name' => '查看报表', 'group' => 'reports']);
        $perm2 = Permission::create(['name' => 'bills.view', 'display_name' => '查看账单', 'group' => 'bills']);
        $perm3 = Permission::create(['name' => 'users.view', 'display_name' => '查看用户', 'group' => 'users']);

        $role1->permissions()->attach([$perm1->id, $perm2->id]);
        $role2->permissions()->attach([$perm2->id, $perm3->id]);
        $user->roles()->attach([$role1->id, $role2->id]);

        $permissions = $user->getAllPermissions();

        $this->assertCount(3, $permissions);
        $this->assertTrue($permissions->contains('reports.view'));
        $this->assertTrue($permissions->contains('bills.view'));
        $this->assertTrue($permissions->contains('users.view'));
    }

    public function test_admin_role_bypasses_permission_check(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        // Admin should pass isAdmin() even without RBAC role
        $this->assertTrue($user->isAdmin());
    }

    public function test_permission_middleware_blocks_unauthorized(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = auth('api')->login($user);

        // Admin routes should be blocked for regular users
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_assign_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = auth('api')->login($admin);

        $user = User::factory()->create();
        $role = Role::create(['name' => 'finance', 'display_name' => '财务']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/admin/users/{$user->id}/roles", [
                'role_ids' => [$role->id],
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => '角色分配成功']);

        $this->assertTrue($user->fresh()->hasRole('finance'));
    }

    public function test_admin_can_list_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = auth('api')->login($admin);

        Role::create(['name' => 'finance', 'display_name' => '财务']);
        Role::create(['name' => 'support', 'display_name' => '客服']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/roles');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
