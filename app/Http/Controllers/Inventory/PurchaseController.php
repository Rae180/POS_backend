<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $purchases = Purchase::with(['supplier', 'user', 'items'])
            ->filter($request->only(['status', 'supplier_id', 'date_from', 'date_to', 'search']))
            ->orderBy($request->get('sort_by', 'purchase_date'), $request->get('sort_order', 'desc'))
            ->paginate(10)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($purchases);
        }

        $suppliers = Supplier::orderBy('name')->get();

        return view('purchases.index', ['purchases' => $purchases, 'suppliers' => $suppliers]);
    }

    public function data(Request $request): JsonResponse
    {
        try {
            $purchases = Purchase::with(['supplier', 'user'])
                ->withCount('items')
                ->filter($request->only(['status', 'supplier_id', 'date_from', 'date_to', 'search']))
                ->orderBy($request->get('sort_by', 'purchase_date'), $request->get('sort_order', 'desc'))
                ->paginate(10);

            return response()->json($purchases);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function create(): View
    {
        $suppliers = Supplier::all();

        return view('purchases.create', ['suppliers' => $suppliers]);
    }

    public function store(PurchaseStoreRequest $request): RedirectResponse|JsonResponse
    {
        try {
            DB::beginTransaction();

            $cartItems = $request->user()->purchaseCart()->get();

            if ($cartItems->isEmpty()) {
                throw new \Exception(__('Purchase cart is empty.'));
            }

            $totalAmount = $cartItems->sum(function ($product) {
                return $product->pivot->quantity * $product->pivot->purchase_price;
            });

            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'user_id' => Auth::id(),
                'purchase_date' => now(),
                'total_amount' => $totalAmount,
                'status' => $request->status ?? 'pending',
                'notes' => $request->notes,
            ]);

            foreach ($cartItems as $product) {
                $purchase->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $product->pivot->quantity,
                    'purchase_price' => $product->pivot->purchase_price,
                ]);

                if ($purchase->status === 'completed') {
                    $product->quantity += $product->pivot->quantity;
                    $product->purchase_price = $product->pivot->purchase_price;
                    $product->save();
                }
            }

            DB::commit();

            $request->user()->purchaseCart()->detach();

            if ($request->wantsJson()) {
                return response()->json($purchase->load(['supplier', 'items']), 201);
            }

            return redirect()->route('purchases.index')
                ->with('success', __('Purchase created successfully!'));

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return redirect()->back()
                ->with('error', __('Failed to create purchase: ') . $e->getMessage())
                ->withInput();
        }
    }
    public function show(Request $request, Purchase $purchase): View|JsonResponse
    {
        $purchase->load(['supplier', 'user', 'items.product']);

        if ($request->wantsJson()) {
            return response()->json($purchase);
        }

        return view('purchases.show', ['purchase' => $purchase]);
    }

    public function update(PurchaseUpdateRequest $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        try {
            DB::beginTransaction();

            $oldStatus = $purchase->status;
            $newStatus = $request->status;

            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $request->total_amount,
                'status' => $newStatus,
                'notes' => $request->notes,
            ]);

            if ($oldStatus !== $newStatus) {
                foreach ($purchase->items as $item) {
                    $product = $item->product;

                    if ($oldStatus === 'completed' && in_array($newStatus, ['pending', 'cancelled'])) {
                        $product->quantity -= $item->quantity;
                        $product->save();
                    }

                    if (in_array($oldStatus, ['pending', 'cancelled']) && $newStatus === 'completed') {
                        $product->quantity += $item->quantity;
                        $product->purchase_price = $item->purchase_price;
                        $product->save();
                    }
                }
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json($purchase->fresh(['supplier', 'items']));
            }

            return redirect()->route('purchases.index')
                ->with('success', __('Purchase updated successfully!'));

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return redirect()->back()
                ->with('error', __('Failed to update purchase: ') . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        try {
            DB::beginTransaction();

            if ($purchase->status === 'completed') {
                foreach ($purchase->items as $item) {
                    $product = $item->product;
                    $product->quantity -= $item->quantity;
                    $product->save();
                }
            }

            $purchase->delete();

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('purchases.index')
                ->with('success', __('Purchase deleted successfully!'));

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return redirect()->back()
                ->with('error', __('Failed to delete purchase: ') . $e->getMessage());
        }
    }

    public function receipt(Purchase $purchase)
    {
        $purchase->load(['supplier', 'user', 'items.product']);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('purchases.receipt', ['purchase' => $purchase]);
        $pdf->setPaper([0, 0, 226.77, 841.89], 'portrait');

        return $pdf->stream("purchase-receipt-{$purchase->id}.pdf");
    }
}