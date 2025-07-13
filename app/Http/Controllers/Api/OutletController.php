<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OutletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('outlets.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = Outlet::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->get('per_page', 15);
        $outlets = $query->orderBy('name')->paginate($perPage);

        // Add statistics for each outlet
        $outlets->getCollection()->transform(function ($outlet) {
            $outlet->stats = [
                'total_users' => $outlet->users()->count(),
                'total_transactions' => $outlet->transactions()->count(),
                'total_products' => $outlet->productStocks()->count(),
                'total_stock_value' => $outlet->productStocks()
                    ->join('products', 'product_stocks.product_id', '=', 'products.id')
                    ->sum(DB::raw('product_stocks.quantity * products.purchase_price')),
            ];
            return $outlet;
        });

        return response()->json([
            'success' => true,
            'data' => $outlets
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('outlets.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:outlets,code',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $outlet = Outlet::create($request->all());

            // Create initial stock records for all products
            $products = Product::where('is_active', true)->get();
            foreach ($products as $product) {
                ProductStock::create([
                    'product_id' => $product->id,
                    'outlet_id' => $outlet->id,
                    'quantity' => 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Outlet created successfully',
                'data' => $outlet
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create outlet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Outlet $outlet): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('outlets.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $outlet->load(['users', 'productStocks.product']);

        // Add detailed statistics
        $outlet->detailed_stats = [
            'users_count' => $outlet->users()->count(),
            'active_users_count' => $outlet->users()->where('is_active', true)->count(),
            'transactions_count' => $outlet->transactions()->count(),
            'transactions_today' => $outlet->transactions()->whereDate('created_at', today())->count(),
            'revenue_today' => $outlet->transactions()
                ->whereDate('created_at', today())
                ->where('status', 'completed')
                ->sum('total_amount'),
            'revenue_this_month' => $outlet->transactions()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('status', 'completed')
                ->sum('total_amount'),
            'products_count' => $outlet->productStocks()->count(),
            'low_stock_products' => $outlet->productStocks()
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->whereRaw('product_stocks.quantity <= products.min_stock')
                ->count(),
            'total_stock_value' => $outlet->productStocks()
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->sum(DB::raw('product_stocks.quantity * products.purchase_price')),
        ];

        return response()->json([
            'success' => true,
            'data' => $outlet
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Outlet $outlet): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('outlets.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:outlets,code,' . $outlet->id,
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'is_active' => 'boolean',
        ]);

        $outlet->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Outlet updated successfully',
            'data' => $outlet
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Outlet $outlet): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('outlets.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if outlet has users
        if ($outlet->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete outlet that has users assigned'
            ], 422);
        }

        // Check if outlet has transactions
        if ($outlet->transactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete outlet that has transaction history'
            ], 422);
        }

        $outlet->delete();

        return response()->json([
            'success' => true,
            'message' => 'Outlet deleted successfully'
        ]);
    }

    /**
     * Get outlet dashboard data
     */
    public function dashboard(Outlet $outlet): JsonResponse
    {
        $today = today();
        $thisMonth = now()->startOfMonth();

        $dashboard = [
            'outlet' => $outlet,
            'stats' => [
                'transactions_today' => $outlet->transactions()->whereDate('created_at', $today)->count(),
                'revenue_today' => $outlet->transactions()
                    ->whereDate('created_at', $today)
                    ->where('status', 'completed')
                    ->sum('total_amount'),
                'transactions_this_month' => $outlet->transactions()->where('created_at', '>=', $thisMonth)->count(),
                'revenue_this_month' => $outlet->transactions()
                    ->where('created_at', '>=', $thisMonth)
                    ->where('status', 'completed')
                    ->sum('total_amount'),
                'active_users' => $outlet->users()->where('is_active', true)->count(),
                'low_stock_products' => $outlet->productStocks()
                    ->join('products', 'product_stocks.product_id', '=', 'products.id')
                    ->whereRaw('product_stocks.quantity <= products.min_stock')
                    ->count(),
            ],
            'recent_transactions' => $outlet->transactions()
                ->with(['customer', 'user'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(),
            'low_stock_products' => $outlet->productStocks()
                ->with('product')
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->whereRaw('product_stocks.quantity <= products.min_stock')
                ->select('product_stocks.*')
                ->take(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $dashboard
        ]);
    }
}
