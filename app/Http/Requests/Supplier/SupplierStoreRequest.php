<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class SupplierStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20'],
            'contact_name' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => __('supplier.validation.email_invalid'),
            'phone.max' => __('supplier.validation.phone_max'),
            'avatar.image' => __('supplier.validation.avatar_image'),
            'avatar.max' => __('supplier.validation.avatar_max'),
        ];
    }
}
