<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log for authenticated users and successful responses
        if (Auth::check() && $response->getStatusCode() < 400) {
            $this->logActivity($request);
        }

        return $response;
    }

    /**
     * Log user activity
     */
    private function logActivity(Request $request): void
    {
        $user = Auth::user();
        $method = $request->method();
        $path = $request->path();

        // Skip logging for certain routes
        $skipRoutes = [
            'api/v1/dashboard',
            'api/v1/audit-logs',
            'sanctum/csrf-cookie',
        ];

        foreach ($skipRoutes as $skipRoute) {
            if (str_contains($path, $skipRoute)) {
                return;
            }
        }

        // Determine event type based on HTTP method and path
        $event = $this->determineEvent($method, $path);

        if (!$event) {
            return; // Skip if we can't determine the event
        }

        // Extract model information from path
        $modelInfo = $this->extractModelInfo($path);

        // Capture new values from request (for creates and updates)
        $newValues = null;
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $newValues = $request->except(['password', 'password_confirmation']);
        }

        // Note: old_values cannot be captured in middleware because it runs after the update
        // For proper old_values tracking, consider using Eloquent Observers
        $oldValues = null;

        try {
            AuditLog::create([
                'model_type' => $modelInfo['type'] ?? null,
                'model_id' => $modelInfo['id'] ?? null,
                'event' => $event,
                'old_values' => $oldValues, // Will be null for now
                'new_values' => $newValues,
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to avoid breaking the application
            Log::error('Failed to create audit log: ' . $e->getMessage());
        }
    }

    /**
     * Determine event type based on HTTP method and path
     */
    private function determineEvent(string $method, string $path): ?string
    {
        return match ($method) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            'GET' => str_contains($path, 'export') || str_contains($path, 'download') ? 'exported' : null,
            default => null,
        };
    }

    /**
     * Extract model information from API path
     */
    private function extractModelInfo(string $path): array
    {
        $segments = explode('/', $path);

        // Look for API version and resource
        $apiIndex = array_search('v1', $segments);
        if ($apiIndex === false || !isset($segments[$apiIndex + 1])) {
            return [];
        }

        $resource = $segments[$apiIndex + 1];
        $modelType = $this->mapResourceToModel($resource);

        $result = ['type' => $modelType];

        // Try to extract ID if present
        if (isset($segments[$apiIndex + 2]) && is_numeric($segments[$apiIndex + 2])) {
            $result['id'] = (int) $segments[$apiIndex + 2];
        }

        return $result;
    }

    /**
     * Map API resource to model class
     */
    private function mapResourceToModel(string $resource): ?string
    {
        $mapping = [
            'products' => 'App\\Models\\Product',
            'categories' => 'App\\Models\\Category',
            'units' => 'App\\Models\\Unit',
            'customers' => 'App\\Models\\Customer',
            'suppliers' => 'App\\Models\\Supplier',
            'transactions' => 'App\\Models\\Transaction',
            'purchases' => 'App\\Models\\Purchase',
            'expenses' => 'App\\Models\\Expense',
            'outlets' => 'App\\Models\\Outlet',
            'users' => 'App\\Models\\User',
            'stocks' => 'App\\Models\\ProductStock',
            'stock-transfers' => 'App\\Models\\StockTransfer',
            'promotions' => 'App\\Models\\Promotion',
        ];

        return $mapping[$resource] ?? null;
    }
}
