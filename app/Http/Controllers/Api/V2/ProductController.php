<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Select only needed columns for better performance
        $query = Product::select([
            'id', 'name', 'sku', 'barcode', 'description', 'category_id', 'unit_id',
            'purchase_price', 'selling_price', 'wholesale_price', 'min_stock',
            'image', 'is_active', 'created_at', 'updated_at'
        ])->with([
            'category:id,name',
            'unit:id,name,symbol',
            'productUnits.unit:id,name,symbol'
        ]);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhereHas('productUnits', function ($qUnit) use ($search) {
                       $qUnit->where('barcode', 'like', "%{$search}%")
                             ->orWhereHas('unit', function ($qU) use ($search) {
                                 $qU->where('name', 'like', "%{$search}%");
                             });
                  });
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Add stock information for specific outlet if provided (optimize with eager loading)
        if ($request->has('outlet_id') || $request->has('with_stock')) {
            /** @var User $user */
            $user = Auth::user();
            $outletId = $request->get('outlet_id') ?? $user->outlet_id ?? 1; // Default to outlet 1

            // Eager load product stocks for specific outlet to avoid N+1 query problem
            $query->with(['productStocks' => function($q) use ($outletId) {
                $q->select('id', 'product_id', 'outlet_id', 'quantity')
                  ->where('outlet_id', $outletId);
            }]);
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        // Transform products to add stock info (already loaded, no additional queries)
        if ($request->has('outlet_id') || $request->has('with_stock')) {
            $products->getCollection()->transform(function ($product) use ($outletId) {
                $stock = $product->productStocks->first(); // Already loaded, no query needed
                $product->stock_quantity = $stock ? $stock->quantity : 0;
                $product->is_low_stock = $product->stock_quantity <= $product->min_stock;
                // Remove productStocks relationship from response (already processed)
                unset($product->productStocks);
                return $product;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Generate SKU if not provided
        if (!isset($data['sku'])) {
            $data['sku'] = 'PRD' . str_pad(Product::count() + 1, 6, '0', STR_PAD_LEFT);
        }


        // Handle image upload if provided
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('product', 'public');
            $data['image'] = $imagePath;
        }

        $product = Product::create($data);

        // Create initial stock for all outlets
        $outlets = \App\Models\Outlet::where('is_active', true)->get();
        foreach ($outlets as $outlet) {
            ProductStock::create([
                'product_id' => $product->id,
                'outlet_id' => $outlet->id,
                'quantity' => 0,
            ]);
        }
        
        // Handle Additional Units
        if (!empty($data['units'])) {
            foreach ($data['units'] as $unitData) {
                // Remove duplicates if validation missed it or just safety
                // Actually validation guarantees valid data
                $product->productUnits()->create($unitData);
            }
        }

        $product->load(['category', 'unit', 'productUnits.unit']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'unit', 'productStocks.outlet']);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            $imagePath = $request->file('image')->store('product', 'public');
            $data['image'] = $imagePath;
        }

        $product->update($data);
        
        // Update/Sync Units
        if (isset($data['units'])) { // Use isset because empty array means delete all
             // Simplest approach: Delete all and recreate. 
             // Ideally we should update existing ones to preserve IDs, but for now this is robust.
             $product->productUnits()->delete();
             
             foreach ($data['units'] as $unitData) {
                 $product->productUnits()->create($unitData);
             }
        }

        $product->load(['category', 'unit', 'productUnits.unit']);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check if product has any transactions
        if ($product->transactionItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product that has transaction history'
            ], 422);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Get product by barcode
     */
    /**
     * Get product by barcode
     */
    public function getByBarcode(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
            'outlet_id' => 'required|exists:outlets,id'
        ]);

        $barcode = $request->barcode;
        $outletId = $request->outlet_id;

        // 1. Try finding by main barcode
        $product = Product::where('barcode', $barcode)
                         ->where('is_active', true)
                         ->with(['category', 'unit:id,name,symbol', 'productUnits.unit:id,name,symbol'])
                         ->first();

        $scannedUnit = null;

        // 2. If not found, try finding in product_units
        if (!$product) {
            // Find the unit first
            $productUnit = \App\Models\ProductUnit::where('barcode', $barcode)
                                ->where('is_active', true)
                                ->with(['product.category', 'product.unit:id,name,symbol', 'product.productUnits.unit:id,name,symbol', 'unit:id,name,symbol'])
                                ->first();
            
            if ($productUnit && $productUnit->product && $productUnit->product->is_active) {
                $product = $productUnit->product;
                $scannedUnit = $productUnit;
            }
        }

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Add stock information
        $stock = $product->productStocks()->where('outlet_id', $outletId)->first();
        $product->stock_quantity = $stock ? $stock->quantity : 0;
        $product->is_low_stock = $product->stock_quantity <= $product->min_stock;

        // If we found a specific unit, we append it to the response
        // This allows the frontend to know which unit price/conversion to use
        if ($scannedUnit) {
            $product->scanned_unit = $scannedUnit;
            // Optionally override the main price/unit for easier frontend handling
            // However, strict frontends might prefer explicit fields.
            // Let's rely on 'scanned_unit' field.
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }
}
