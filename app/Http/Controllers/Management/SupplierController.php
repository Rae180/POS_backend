<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SupplierStoreRequest;
use App\Http\Requests\Supplier\SupplierUpdateRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierController extends Controller
{

    public function index(Request $request)
    {
        $suppliers = Supplier::latest()->paginate();

        if ($request->wantsJson()) {
            return response()->json($suppliers);
        }

        return view('suppliers.index', ['suppliers' => $suppliers]);
    }

    public function create(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        return view('suppliers.create');
    }

    public function store(SupplierStoreRequest $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $supplierData = $request->validated();

        if ($request->hasFile('avatar')) {
            $supplierData['avatar'] = $request->file('avatar')->store('suppliers', 'public');
        }

        $supplier = Supplier::create($supplierData);

        if ($request->wantsJson()) {
            return response()->json($supplier, 201);
        }

        return redirect()->route('suppliers.index')
            ->with('success', __('supplier.success_creating'));
    }
    public function update(SupplierUpdateRequest $request, Supplier $supplier): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $supplierData = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($supplier->avatar) {
                Storage::disk('public')->delete($supplier->avatar);
            }
            $supplierData['avatar'] = $request->file('avatar')->store('suppliers', 'public');
        }

        $supplier->update($supplierData);

        if ($request->wantsJson()) {
            return response()->json($supplier, 200);
        }

        return redirect()->route('suppliers.index')
            ->with('success', __('supplier.success_updating'));
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->avatar) {
            Storage::disk('public')->delete($supplier->avatar);
        }

        $supplier->delete();

        return response()->json([
            'success' => true
        ]);
    }
}
