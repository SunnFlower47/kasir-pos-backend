<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    /**
     * Get all settings
     */
    public function index(): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('settings.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $settings = Setting::all()->groupBy(function ($setting) {
            $parts = explode('_', $setting->key);
            return $parts[0] ?? 'general';
        });

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('settings.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'required|in:string,integer,boolean,json',
        ]);

        foreach ($request->settings as $settingData) {
            $key = $settingData['key'];
            $value = $settingData['value'];

            // Handle logo uploads - if value is base64, convert to file storage
            if (in_array($key, ['company_logo', 'app_logo']) && $value) {
                // Check if it's base64 image data
                if (preg_match('/^data:image\/(\w+);base64,/', $value, $matches)) {
                    try {
                        $value = $this->saveBase64Image($value, $key);
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to save logo: ' . $e->getMessage()
                        ], 500);
                    }
                }
                // If it's a file path starting with 'logos/', it's already saved
                // If it's empty/null, keep it as is
            }

            Setting::set($key, $value, $settingData['type']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }

    /**
     * Upload logo file
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('settings.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // 2MB max
            'type' => 'required|in:company_logo,app_logo',
        ]);

        try {
            // Delete old logo if exists
            $oldLogoPath = Setting::get($request->type, null);
            if ($oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('logos', 'public');

            // Save path to settings
            Setting::set($request->type, $logoPath, 'string');

            // Get full URL
            $logoUrl = url('storage/' . $logoPath);

            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully',
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
     * Delete logo
     */
    public function deleteLogo(Request $request, string $type): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('settings.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!in_array($type, ['company_logo', 'app_logo'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid logo type'
            ], 400);
        }

        try {
            $logoPath = Setting::get($type, null);
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            Setting::set($type, '', 'string');

            return response()->json([
                'success' => true,
                'message' => 'Logo deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete logo', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);
            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Failed to delete logo. Please try again later.'
                    : 'Failed to delete logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save base64 image to file storage
     */
    private function saveBase64Image(string $base64Data, string $key): string
    {
        // Extract image data and extension
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64Data, $matches)) {
            throw new \Exception('Invalid base64 image data');
        }

        $extension = $matches[1];
        $imageData = base64_decode($matches[2]);

        if ($imageData === false) {
            throw new \Exception('Failed to decode base64 image data');
        }

        // Delete old logo if exists
        $oldLogoPath = Setting::get($key, null);
        if ($oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        // Generate unique filename
        $filename = $key . '_' . time() . '.' . $extension;
        $logoPath = 'logos/' . $filename;

        // Save to storage
        Storage::disk('public')->put($logoPath, $imageData);

        return $logoPath;
    }

    /**
     * Get specific setting group
     */
    public function getGroup(string $group): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('settings.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $settings = Setting::where('key', 'like', $group . '_%')->get();

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update specific setting group
     */
    public function updateGroup(Request $request, string $group): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('settings.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, $group . '_')) {
                $type = match (true) {
                    is_bool($value) => 'boolean',
                    is_int($value) => 'integer',
                    is_array($value) => 'json',
                    default => 'string',
                };
                Setting::set($key, $value, $type);
            }
        }

        return response()->json([
            'success' => true,
            'message' => ucfirst($group) . ' settings updated successfully'
        ]);
    }

    /**
     * Backup database
     */
    public function backup(): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        // SECURITY: Database backup contains ALL tenant data. Only System Admin can perform this.
        if (!$user || !$user->hasRole('System Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only System Admin can perform system backups.'
            ], 403);
        }

        try {
            $filename = 'backup-' . now()->format('Y-m-d-H-i-s') . '.sql';
            $path = storage_path('app/backups/' . $filename);

            // Create backups directory if it doesn't exist
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            // For SQLite, we can simply copy the database file
            if (config('database.default') === 'sqlite') {
                $dbPath = database_path('database.sqlite');
                if (file_exists($dbPath)) {
                    copy($dbPath, storage_path('app/backups/database-' . now()->format('Y-m-d-H-i-s') . '.sqlite'));
                }
            } else {
                // For MySQL/PostgreSQL, you would use mysqldump or pg_dump
                // This is a simplified implementation
                Artisan::call('backup:run');
            }

            return response()->json([
                'success' => true,
                'message' => 'Database backup created successfully',
                'data' => [
                    'filename' => $filename,
                    'created_at' => now(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get backup list
     */
    public function backups(): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->hasRole('System Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only System Admin can view system backups.'
            ], 403);
        }

        $backupPath = storage_path('app/backups');
        $backups = [];

        if (is_dir($backupPath)) {
            $files = scandir($backupPath);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && (str_ends_with($file, '.sql') || str_ends_with($file, '.sqlite'))) {
                    $filePath = $backupPath . '/' . $file;
                    $backups[] = [
                        'filename' => $file,
                        'size' => filesize($filePath),
                        'created_at' => date('Y-m-d H:i:s', filemtime($filePath)),
                    ];
                }
            }
        }

        // Sort by creation date (newest first)
        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return response()->json([
            'success' => true,
            'data' => $backups
        ]);
    }

    /**
     * Download backup file
     */
    public function downloadBackup(string $filename)
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->hasRole('System Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only System Admin can download system backups.'
            ], 403);
        }

        $filePath = storage_path('app/backups/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
        }

        return response()->download($filePath);
    }

    /**
     * Delete backup file
     */
    public function deleteBackup(string $filename): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->hasRole('System Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only System Admin can delete system backups.'
            ], 403);
        }

        $filePath = storage_path('app/backups/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
        }

        unlink($filePath);

        return response()->json([
            'success' => true,
            'message' => 'Backup file deleted successfully'
        ]);
    }

    /**
     * Get system information
     */
    public function systemInfo(): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->hasRole('System Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $info = [
            'app' => [
                'name' => config('app.name'),
                'version' => '1.0.0',
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'driver' => config('database.connections.' . config('database.default') . '.driver'),
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'storage' => [
                'disk_free_space' => disk_free_space('/'),
                'disk_total_space' => disk_total_space('/'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }
}
