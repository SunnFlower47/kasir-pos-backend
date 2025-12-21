<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user && method_exists($user, 'can') && $user->can('products.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:products,name',
            'sku' => 'nullable|string|unique:products,sku|max:100',
            'barcode' => 'nullable|string|unique:products,barcode|max:100',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
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
            'name.required' => 'Product name is required',
            'name.unique' => 'Product name already exists',
            'sku.unique' => 'SKU already exists',
            'barcode.unique' => 'Barcode already exists',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'unit_id.required' => 'Unit is required',
            'unit_id.exists' => 'Selected unit does not exist',
            'purchase_price.required' => 'Purchase price is required',
            'purchase_price.min' => 'Purchase price must be at least 0',
            'selling_price.required' => 'Selling price is required',
            'selling_price.min' => 'Selling price must be at least 0',
            'min_stock.required' => 'Minimum stock is required',
            'min_stock.min' => 'Minimum stock must be at least 0',
        ];
    }
}
