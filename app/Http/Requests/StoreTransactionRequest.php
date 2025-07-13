<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user && method_exists($user, 'can') && $user->can('transactions.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outlet_id' => 'nullable|exists:outlets,id',
            'customer_id' => 'nullable|exists:customers,id',
            'transaction_date' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,qris,e_wallet',
            'notes' => 'nullable|string',

            // Transaction items
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outlet_id.required' => 'Outlet is required',
            'outlet_id.exists' => 'Selected outlet does not exist',
            'customer_id.exists' => 'Selected customer does not exist',
            'paid_amount.required' => 'Paid amount is required',
            'paid_amount.min' => 'Paid amount must be at least 0',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method',
            'items.required' => 'Transaction items are required',
            'items.min' => 'At least one item is required',
            'items.*.product_id.required' => 'Product is required for each item',
            'items.*.product_id.exists' => 'Selected product does not exist',
            'items.*.quantity.required' => 'Quantity is required for each item',
            'items.*.quantity.min' => 'Quantity must be at least 1',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that paid amount is sufficient
            $subtotal = 0;
            $totalDiscount = $this->discount_amount ?? 0;
            $totalTax = $this->tax_amount ?? 0;

            if ($this->has('items')) {
                foreach ($this->items as $item) {
                    $unitPrice = $item['unit_price'] ?? 0;
                    $quantity = $item['quantity'] ?? 0;
                    $itemDiscount = $item['discount_amount'] ?? 0;
                    $subtotal += ($unitPrice * $quantity) - $itemDiscount;
                }
            }

            $totalAmount = $subtotal + $totalTax - $totalDiscount;
            $paidAmount = $this->paid_amount ?? 0;

            if ($paidAmount < $totalAmount) {
                $validator->errors()->add('paid_amount', 'Paid amount is insufficient');
            }
        });
    }
}
