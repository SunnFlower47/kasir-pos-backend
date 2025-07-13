<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole(['Super Admin', 'Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
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
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole(['Super Admin', 'Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
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
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole(['Super Admin', 'Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $query = AuditLog::whereBetween('created_at', [$dateFrom, $dateTo]);

        $stats = [
            'total_logs' => $query->count(),
            'events_breakdown' => $query->selectRaw('event, COUNT(*) as count')
                ->groupBy('event')
                ->orderBy('count', 'desc')
                ->get(),
            'models_breakdown' => $query->selectRaw('model_type, COUNT(*) as count')
                ->groupBy('model_type')
                ->orderBy('count', 'desc')
                ->get(),
            'users_breakdown' => $query->with('user')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderBy('count', 'desc')
                ->take(10)
                ->get(),
            'daily_activity' => $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Clean old audit logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole(['Super Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
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
