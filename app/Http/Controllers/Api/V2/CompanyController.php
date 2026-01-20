<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Update Company/Tenant Profile
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
        ]);

        $tenant = $request->user()->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $tenant->update([
            'name' => $request->company_name,
            'address' => $request->address,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Informasi bisnis berhasil diperbarui',
            'data' => $tenant
        ]);
    }
}

