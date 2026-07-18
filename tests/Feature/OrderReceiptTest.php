<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OrderReceiptTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    use RefreshDatabase;


    public function test_order_receipt_returns_expected_structure(): void
    {
        $order = \App\Models\Order::factory()
            ->has(\App\Models\OrderItem::factory()->count(2), 'items')
            ->has(\App\Models\Payment::factory(), 'payments')
            ->create();

        $this->actingAs($order->user)
            ->getJson(route('orders.receipt', $order))
            ->assertOk()
            ->assertJsonStructure([
                'receipt_number',
                'date',
                'cashier',
                'customer',
                'items' => [['product_name', 'quantity', 'unit_price', 'subtotal']],
                'subtotal',
                'total',
                'amount_paid',
                'balance_due',
                'is_fully_paid',
            ]);
    }

    public function test_order_refund_restocks_items_and_reverses_payment(): void
    {
        $product = \App\Models\Product::factory()->create(['quantity' => 5]);

        $order = \App\Models\Order::factory()->create();
        $item = $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 20,
        ]);
        $order->payments()->create(['amount' => 20, 'user_id' => $order->user_id]);

        $this->actingAs($order->user)
            ->postJson(route('orders.refund', $order))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(7, $product->fresh()->quantity); // 5 + 2 restocked
        $this->assertEquals('refunded', $order->fresh()->status);
        $this->assertEquals(0, $order->fresh()->receivedAmount()); // 20 - 20
    }

    public function test_order_total_applies_discount_then_tax(): void
    {
        $order = \App\Models\Order::factory()->create([
            'discount_percent' => 10,
            'tax_rate' => 5,
        ]);
        $order->items()->create(['product_id' => \App\Models\Product::factory()->create()->id, 'quantity' => 1, 'price' => 100]);

        // subtotal 100 -> discount 10% = 90 -> tax 5% of 90 = 4.5 -> total 94.5
        $this->assertEquals(100.0, $order->subtotal());
        $this->assertEquals(10.0, $order->discountAmount());
        $this->assertEquals(4.5, $order->taxAmount());
        $this->assertEquals(94.5, $order->total());
    }
    public function test_api_orders_index_returns_json(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        \App\Models\Order::factory()->count(3)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    }

    public function test_product_can_be_created_with_a_category(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'Test Soda',
                'barcode' => '123456789',
                'price' => 2.50,
                'quantity' => 10,
                'category' => 'Beverages',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('products', ['name' => 'Test Soda', 'category' => 'Beverages']);
    }

    public function test_product_rejects_invalid_category(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'Test Item',
                'barcode' => '987654321',
                'price' => 1.00,
                'quantity' => 5,
                'category' => 'NotARealCategory',
            ])
            ->assertStatus(422);
    }

    public function test_order_receipt_includes_id(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $cashier = \App\Models\User::factory()->create();
        $cashier->assignRole('cashier');

        $order = \App\Models\Order::factory()->create();

        $this->actingAs($cashier)
            ->getJson("/api/orders/{$order->id}/receipt")
            ->assertOk()
            ->assertJsonPath('id', $order->id);
    }
}
