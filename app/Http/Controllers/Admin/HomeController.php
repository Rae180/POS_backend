<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function __invoke(): Factory|View|\Illuminate\View\View
    {
        $ordersCount = 0;
        $income = 0.0;
        $incomeToday = 0.0;
        $today = today();

        Order::with(['items', 'payments'])
            ->lazy()
            ->each(function (Order $order) use (&$ordersCount, &$income, &$incomeToday, $today): void {
                $ordersCount++;
                $applied = min($order->receivedAmount(), $order->total());
                $income += $applied;

                if ($order->created_at->greaterThanOrEqualTo($today)) {
                    $incomeToday += $applied;
                }
            });

        return view('home', [
            'orders_count' => $ordersCount,
            'income' => $income,
            'income_today' => $incomeToday,
            'customers_count' => Customer::count(),
            'low_stock_products' => Product::lowStock()->get(),
            'best_selling_products' => Product::bestSelling()->get(),
            'current_month_products' => Product::currentMonthBestSelling()->get(),
            'past_months_products' => Product::pastMonthsHotProducts()->get(),
        ]);
    }
}