<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('audit-logs.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing audit-logs.view permission'
            ], 403);
        }

        $query = AuditLog::with(['user']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by model type
        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by event
        if ($request->has('event')) {
            $query->where('event', $request->event);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search by IP address
        if ($request->has('ip_address')) {
            $query->where('ip_address', 'like', '%' . $request->ip_address . '%');
        }

        $perPage = $request->get('per_page', 15);
        $auditLogs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $auditLogs
        ]);
    }

    /**
     * Display the specified audit log
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('audit-logs.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing audit-logs.view permission'
            ], 403);
        }

        $auditLog->load(['user']);

        return response()->json([
            'success' => true,
            'data' => $auditLog
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('audit-logs.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing audit-logs.view permission'
            ], 403);
        }

        try {
            $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('date_to', now()->toDateString());

            // Base query for date range - ensure proper datetime format
            $dateFromStr = $dateFrom . ' 00:00:00';
            $dateToStr = $dateTo . ' 23:59:59';
            $baseQuery = AuditLog::whereBetween('created_at', [$dateFromStr, $dateToStr]);

            // Execute each query separately (using clone to avoid query builder state issues)
            $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';
            $dateExpr = $isSqlite ? "date(created_at)" : "DATE(created_at)";

            $stats = [
                'total_logs' => (clone $baseQuery)->count(),
                'events_breakdown' => (clone $baseQuery)
                    ->selectRaw('event, COUNT(*) as count')
                    ->groupBy('event')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'event' => $item->event,
                            'count' => (int) $item->count
                        ];
                    }),
                'models_breakdown' => (clone $baseQuery)
                    ->selectRaw('model_type, COUNT(*) as count')
                    ->groupBy('model_type')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'model_type' => $item->model_type,
                            'count' => (int) $item->count
                        ];
                    }),
                'users_breakdown' => (clone $baseQuery)
                    ->with('user:id,name,email')
                    ->selectRaw('user_id, COUNT(*) as count')
                    ->groupBy('user_id')
                    ->orderBy('count', 'desc')
                    ->take(10)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'user_id' => $item->user_id,
                            'user' => $item->user ? [
                                'id' => $item->user->id,
                                'name' => $item->user->name,
                                'email' => $item->user->email,
                            ] : null,
                            'count' => (int) $item->count
                        ];
                    }),
                'daily_activity' => (clone $baseQuery)
                    ->selectRaw("{$dateExpr} as date, COUNT(*) as count")
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'date' => $item->date,
                            'count' => (int) $item->count
                        ];
                    }),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error fetching audit log statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean old audit logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('audit-logs.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing audit-logs.delete permission'
            ], 403);
        }

        $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $cutoffDate = now()->subDays($request->days);
        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deletedCount} audit log entries older than {$request->days} days",
            'data' => [
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate,
            ]
        ]);
    }
}
