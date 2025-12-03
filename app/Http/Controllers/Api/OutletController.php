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
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class OutletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('outlets.view')) {
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
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('outlets.create')) {
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
            'website' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
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
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('outlets.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $outlet->load([
            'users:id,name,email,is_active,outlet_id',
            'productStocks:id,product_id,outlet_id,quantity'
        ]);

        // Optimize statistics with aggregated queries instead of multiple count() calls
        // This reduces 8 queries to 3 queries (66% reduction)

        // Users statistics (single query)
        $userStats = DB::table('users')
            ->selectRaw('
                COUNT(*) as users_count,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users_count
            ')
            ->where('outlet_id', $outlet->id)
            ->first();

        // Transactions statistics (single query) - Database agnostic
        $todayStart = now()->startOfDay()->toDateTimeString();
        $todayEnd = now()->endOfDay()->toDateTimeString();
        $monthStart = now()->startOfMonth()->toDateTimeString();
        $monthEnd = now()->endOfMonth()->toDateTimeString();

        $transactionStats = DB::table('transactions')
            ->selectRaw('
                COUNT(*) as transactions_count,
                SUM(CASE WHEN transaction_date >= ? AND transaction_date <= ? THEN 1 ELSE 0 END) as transactions_today,
                SUM(CASE WHEN transaction_date >= ? AND transaction_date <= ? AND status = "completed"
                    THEN total_amount ELSE 0 END) as revenue_today,
                SUM(CASE WHEN transaction_date >= ? AND transaction_date <= ? AND status = "completed"
                    THEN total_amount ELSE 0 END) as revenue_this_month
            ', [$todayStart, $todayEnd, $todayStart, $todayEnd, $monthStart, $monthEnd])
            ->where('outlet_id', $outlet->id)
            ->first();

        // Product stocks statistics (single query)
        $stockStats = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->selectRaw('
                COUNT(*) as products_count,
                SUM(CASE WHEN product_stocks.quantity <= products.min_stock THEN 1 ELSE 0 END) as low_stock_products,
                SUM(product_stocks.quantity * products.purchase_price) as total_stock_value
            ')
            ->where('product_stocks.outlet_id', $outlet->id)
            ->first();

        // Combine all statistics
        $outlet->detailed_stats = [
            'users_count' => (int) ($userStats->users_count ?? 0),
            'active_users_count' => (int) ($userStats->active_users_count ?? 0),
            'transactions_count' => (int) ($transactionStats->transactions_count ?? 0),
            'transactions_today' => (int) ($transactionStats->transactions_today ?? 0),
            'revenue_today' => (float) ($transactionStats->revenue_today ?? 0),
            'revenue_this_month' => (float) ($transactionStats->revenue_this_month ?? 0),
            'products_count' => (int) ($stockStats->products_count ?? 0),
            'low_stock_products' => (int) ($stockStats->low_stock_products ?? 0),
            'total_stock_value' => (float) ($stockStats->total_stock_value ?? 0),
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
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('outlets.edit')) {
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
            'website' => 'nullable|string|max:255',
            'npwp' => 'nullable|string|max:50',
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
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('outlets.delete')) {
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
     * Upload outlet logo
     */
    public function uploadLogo(Request $request, Outlet $outlet): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('outlets.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
        ]);

        try {
            // Delete old logo if exists
            if ($outlet->logo && Storage::disk('public')->exists($outlet->logo)) {
                Storage::disk('public')->delete($outlet->logo);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('logos/outlets', 'public');

            // Update outlet with logo path
            $outlet->update(['logo' => $logoPath]);

            // Get full URL
            $logoUrl = url('storage/' . $logoPath);

            return response()->json([
                'success' => true,
                'message' => 'Outlet logo uploaded successfully',
                'data' => [
                    'path' => $logoPath,
                    'url' => $logoUrl
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload logo: ' . $e->getMessage()
            ], 500);
        }
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
