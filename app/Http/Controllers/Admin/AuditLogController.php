<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->latest();

        // Optional: Add filters here if needed later (e.g. by event, user, tenant)
        // For now, System Admins see EVERYTHING.
        
        $logs = $query->paginate(20);

        return view('admin.audit-logs.index', compact('logs'));
    }
}
