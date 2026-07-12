<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_shift_open_and_close_calculates_variance(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)
            ->postJson(route('shift.open'), ['opening_float' => 100])
            ->assertOk();

        $shift = Shift::current();

        $order = Order::factory()->create();
        $order->payments()->create([
            'amount' => 50,
            'user_id' => $cashier->id,
            'shift_id' => $shift->id,
        ]);

        // opening_float 100 + payments 50 = expected 150
        $this->actingAs($cashier)
            ->postJson(route('shift.close'), ['closing_cash_counted' => 150])
            ->assertOk()
            ->assertJson([
                'expected_cash' => 150.0,
                'variance' => 0.0,
            ]);
    }

    public function test_cannot_open_a_second_shift_while_one_is_open(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)->postJson(route('shift.open'), ['opening_float' => 100]);

        $this->actingAs($cashier)
            ->postJson(route('shift.open'), ['opening_float' => 50])
            ->assertStatus(400);
    }
}