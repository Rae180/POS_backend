<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderStoreRequest;
use App\Http\Resources\OrderReceiptResource;
use App\Http\Requests\Order\OrderRefundRequest;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use App\Models\Shift;

class OrderController extends Controller
{

    public function apiIndex(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::query()
            ->with(['items.product', 'payments', 'customer'])
            ->when($request->input('start_date'), function ($query, $startDate): void {
                $query->where('created_at', '>=', $startDate);
            })
            ->when($request->input('end_date'), function ($query, string $endDate): void {
                $query->where('created_at', '<=', $endDate . ' 23:59:59');
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => OrderReceiptResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        $orders = Order::query()
            ->with(['items.product', 'payments', 'customer'])
            ->when($request->input('start_date'), function ($query, $startDate): void {
                $query->where('created_at', '>=', $startDate);
            })
            ->when($request->input('end_date'), function ($query, string $endDate): void {
                $query->where('created_at', '<=', $endDate . ' 23:59:59');
            })
            ->latest()
            ->paginate(10);

        $total = $orders->sum(fn($order) => $order->total());
        $receivedAmount = $orders->sum(fn($order) => $order->receivedAmount());

        return view('orders.index', ['orders' => $orders, 'total' => $total, 'receivedAmount' => $receivedAmount]);
    }

    public function refund(OrderRefundRequest $request, Order $order): \Illuminate\Http\JsonResponse
    {
        if ($order->isRefunded()) {
            return response()->json([
                'success' => false,
                'message' => __('order.already_refunded'),
            ], 400);
        }

        try {
            DB::transaction(function () use ($order, $request): void {
                $order->load('items.product', 'payments');

                foreach ($order->items as $item) {
                    $item->product->increment('quantity', $item->quantity);
                }

                // Reverse each payment individually, matching its method
                foreach ($order->payments as $payment) {
                    if ($payment->amount > 0) {
                        $order->payments()->create([
                            'amount' => -$payment->amount,
                            'payment_method' => $payment->payment_method,
                            'user_id' => $request->user()->id,
                            'shift_id' => Shift::current()?->id,
                        ]);
                    }
                }

                $order->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'refund_reason' => $request->reason,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => __('order.refunded_successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function receipt(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load(['items.product', 'payments', 'customer', 'user']);

        return response()->json(new OrderReceiptResource($order));
    }



    public function store(OrderStoreRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($request) {
                // Create order
                $order = Order::create([
                    'customer_id' => $request->customer_id,
                    'user_id' => $request->user()->id,
                    'discount_percent' => $request->discount_percent ?? 0,
                    'tax_rate' => Setting::where('key', 'tax_rate')->value('value') ?? 0,
                ]);

                // Get cart items
                $cartItems = $request->user()->cart()->get();

                if ($cartItems->isEmpty()) {
                    throw new \Exception(__('cart.empty'));
                }

                // Create order items and update product quantities
                foreach ($cartItems as $item) {
                    $this->createOrderItem($order, $item);
                    $this->reduceProductStock($item);
                }

                // Clear cart
                $request->user()->cart()->detach();

                // Create payment
                $order->payments()->create([
                    'amount' => $request->amount,
                    'payment_method' => $request->payment_method,
                    'user_id' => $request->user()->id,
                    'shift_id' => Shift::current()?->id,
                ]);

                return $order;
            });

            return response()->json([
                'success' => true,
                'message' => __('order.created_successfully'),
                'order_id' => $order->id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function partialPayment(Request $request)
    {
        $order = Order::findOrFail($request->input('order_id'));
        $remainingAmount = $order->total() - $order->receivedAmount();

        if ($request->input('amount') > $remainingAmount) {
            return redirect()->route('orders.index')
                ->withErrors(__('order.amount_exceeds_balance'));
        }

        DB::transaction(function () use ($order, $request): void {
            $order->payments()->create([
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'user_id' => auth()->id,
                'shift_id' => Shift::current()?->id,
            ]);
        });

        return redirect()->route('orders.index')
            ->with('success', __('order.partial_payment_success', [
                'amount' => config('settings.currency_symbol') . number_format($request->amount, 2)
            ]));
    }

    /**
     * Create an order item from cart item.
     */
    private function createOrderItem(Order $order, $item): void
    {
        $order->items()->create([
            'price' => $item->price * $item->pivot->quantity,
            'quantity' => $item->pivot->quantity,
            'product_id' => $item->id,
        ]);
    }

    /**
     * Reduce product stock based on cart quantity.
     */
    private function reduceProductStock($item): void
    {
        $item->decrement('quantity', $item->pivot->quantity);
    }
}
