<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_expected_cash_only_counts_cash_payments(): void
    {
        $user = User::first() ?? User::factory()->create();

        $shift = Shift::create([
            'opened_by' => $user->id,
            'opening_float' => 100,
            'opened_at' => now(),
        ]);

        $order = Order::factory()->create();

        $order->payments()->create([
            'amount' => 50,
            'payment_method' => Payment::METHOD_CASH,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
        ]);

        $order->payments()->create([
            'amount' => 200,
            'payment_method' => Payment::METHOD_CARD,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
        ]);

        // opening_float 100 + cash 50 = 150, card 200 excluded
        $this->assertEquals(150.0, $shift->fresh()->expectedCash());
        $this->assertEquals(250.0, $shift->fresh()->totalPayments());
    }

    public function test_refund_reverses_each_payment_with_matching_method(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::factory()->create(['quantity' => 5]);
        $order = Order::factory()->create();
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
        $order->payments()->create([
            'amount' => 100,
            'payment_method' => Payment::METHOD_CARD,
            'user_id' => $order->user_id,
        ]);

        $this->actingAs($admin)
            ->post(route('orders.refund', $order))
            ->assertOk();

        $reversal = $order->payments()->where('amount', '<', 0)->first();

        $this->assertNotNull($reversal, 'Expected a reversal payment to exist');
        $this->assertEquals(-100, $reversal->amount);
        $this->assertEquals(Payment::METHOD_CARD, $reversal->payment_method);
        $this->assertEquals(6, $product->fresh()->quantity);
    }
}