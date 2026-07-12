<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRefundPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cashier_cannot_access_refund_route(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $order = Order::factory()->create();

        $this->actingAs($cashier)
            ->post(route('orders.refund', $order))
            ->assertForbidden();
    }

    public function test_admin_can_access_refund_route(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $order = Order::factory()->create();

        $this->actingAs($admin)
            ->post(route('orders.refund', $order))
            ->assertOk();
    }
}