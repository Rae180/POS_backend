<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class OrderRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tighten once roles/permissions exist
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}