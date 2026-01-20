<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SystemToolController extends Controller
{
    public function index()
    {
        // Simple dashboard for tools
        return view('admin.system-tools.index');
    }

    public function clearCache(Request $request)
    {
        try {
            Artisan::call('optimize:clear');
            return back()->with('success', 'System cache cleared (optimize:clear)!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }

    public function logs(Request $request)
    {
        $logFile = storage_path('logs/laravel.log');
        $lines = 100;
        $content = [];

        if (File::exists($logFile)) {
            // Simple tail implementation
            $file = file($logFile);
            $count = count($file);
            // Get last N lines
            $start = max(0, $count - $lines);
            $content = array_slice($file, $start, $lines);
            
            // Reverse to show newest first
            $content = array_reverse($content);
        } else {
            $content = ['Log file not found at ' . $logFile];
        }

        return view('admin.system-tools.logs', compact('content'));
    }

    public function maintenance()
    {
        $isDown = app()->isDownForMaintenance();
        return view('admin.system-tools.maintenance', compact('isDown'));
    }

    public function toggleMaintenance(Request $request)
    {
        // Check current status
        $isDown = app()->isDownForMaintenance();
        
        try {
            if ($isDown) {
                Artisan::call('up');
                $message = 'Application is now LIVE.';
            } else {
                // Use a secret to allow admin access if needed, but for now simple down
                Artisan::call('down', [
                    '--secret' => 'admin-access-secret' 
                ]);
                $message = 'Application is now in MAINTENANCE MODE.';
            }
            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to toggle maintenance: ' . $e->getMessage());
        }
    }
}
