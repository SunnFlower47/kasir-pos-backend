<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->can('products.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')->id ?? null;
        $tenantId = Auth::user()->tenant_id;

        return [
            'name' => [
                'required', 
                'string', 
                'max:255', 
                \Illuminate\Validation\Rule::unique('products')->where('tenant_id', $tenantId)->ignore($productId)
            ],
            'sku' => [
                'nullable', 
                'string', 
                'max:100', 
                \Illuminate\Validation\Rule::unique('products')->where('tenant_id', $tenantId)->ignore($productId)
            ],
            'barcode' => [
                'nullable', 
                'string', 
                'max:100', 
                \Illuminate\Validation\Rule::unique('products')->where('tenant_id', $tenantId)->ignore($productId)
            ],
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'image' => 'nullable|string',
            'is_active' => 'boolean',
            
            // Multiple Units Validation
            'units' => 'nullable|array',
            'units.*.unit_id' => 'required|exists:units,id|distinct',
            'units.*.conversion_factor' => 'required|numeric|min:0.01',
            'units.*.purchase_price' => 'nullable|numeric|min:0',
            'units.*.selling_price' => 'nullable|numeric|min:0',
            'units.*.wholesale_price' => 'nullable|numeric|min:0',
            'units.*.barcode' => [
                'nullable',
                'string',
                'max:100',
                'distinct',
                // Check uniqueness in products table (main barcodes)
                \Illuminate\Validation\Rule::unique('products', 'barcode')->where('tenant_id', $tenantId)->ignore($productId),
                // Check uniqueness in product_units table
                \Illuminate\Validation\Rule::unique('product_units', 'barcode')->where('tenant_id', $tenantId)->ignore($productId, 'product_id'),
            ],
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
