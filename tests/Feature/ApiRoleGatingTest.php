<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRoleGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }



    public function test_cashier_cannot_delete_supplier_via_api(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $supplier = \App\Models\Supplier::factory()->create();

        $this->actingAs($cashier, 'sanctum')
            ->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertForbidden();
    }

    public function test_login_does_not_overwrite_existing_admin_role(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('secret123')]);
        $admin->assignRole('admin');

        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->assertTrue($admin->fresh()->hasRole('admin'));
        $this->assertFalse($admin->fresh()->hasRole('cashier'));
    }
}