<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'receipt_number' => 'ORD-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'date' => $this->created_at->toIso8601String(),
            'cashier' => $this->user->getFullname(),
            'customer' => $this->getCustomerName(),

            'items' => $this->items->map(fn($item) => [
                'product_name' => $item->product->name,
                'barcode' => $item->product->barcode,
                'quantity' => $item->quantity,
                'unit_price' => round($item->unitPrice(), 2),
                'subtotal' => round($item->subtotal(), 2),
            ]),

            'subtotal' => round($this->subtotal(), 2),
            'discount_percent' => (float) $this->discount_percent,
            'discount_amount' => $this->discountAmount(),
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->taxAmount(),
            'total' => $this->total(),

            'payments' => $this->payments->map(fn($p) => [
                'amount' => round((float) $p->amount, 2),
                'payment_method' => $p->payment_method,
                'paid_at' => $p->created_at->toIso8601String(),
            ]),

            'amount_paid' => round($this->receivedAmount(), 2),
            'balance_due' => round($this->remainingBalance(), 2),
            'is_fully_paid' => $this->isFullyPaid(),

            'currency_symbol' => config('settings.currency_symbol', '$'),
            'status' => $this->status,
            'is_refunded' => $this->isRefunded(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
        ];
    }
}