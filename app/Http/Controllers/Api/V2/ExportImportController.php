<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Exports\ProductsExport;
use App\Exports\SalesReportExport;
use App\Exports\FinancialReportExport;
use App\Exports\EnhancedReportExport;
use App\Exports\AdvancedReportExport;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;

class ExportImportController extends Controller
{
    /**
     * Export data ke Excel
     * GET /api/v1/export/{type}/excel?params...
     */
    public function exportExcel(Request $request, string $type): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check permission
        if (!$user->can('export.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing export.view permission'
            ], 403);
        }

        $params = $request->all();
        $params['tenant_id'] = $user->tenant_id;
        $params['business_id'] = $user->business_id;
        $filename = $this->generateFilename($type, $params);

        try {
            $response = match($type) {
                'products' => Excel::download(new ProductsExport($params), $filename),
                'sales-report' => Excel::download(new SalesReportExport($params), $filename),
                'financial-report' => Excel::download(new FinancialReportExport($params), $filename),
                'enhanced-report' => Excel::download(new EnhancedReportExport($params), $filename),
                'advanced-report' => Excel::download(new AdvancedReportExport($params), $filename),
                default => response()->json([
                    'success' => false,
                    'message' => 'Export type not found'
                ], 404)
            };

            // Add CORS headers for file download
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Excel Export Error', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data ke PDF
     * GET /api/v1/export/{type}/pdf?params...
     */
    public function exportPdf(Request $request, string $type)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check permission
        if (!$user->can('export.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing export.view permission'
            ], 403);
        }

        $params = $request->all();
        $params['tenant_id'] = $user->tenant_id;
        $params['business_id'] = $user->business_id;

        try {
            $data = $this->getExportData($type, $params);
            $view = "exports.{$type}";

            // Check if view exists
            if (!view()->exists($view)) {
                return response()->json([
                    'success' => false,
                    'message' => 'PDF view not found for type: ' . $type
                ], 404);
            }

            $pdf = Pdf::loadView($view, $data);
            $filename = $this->generateFilename($type, $params, 'pdf');

            $response = $pdf->download($filename);

            // Add CORS headers for file download
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PDF export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download template Excel untuk import
     * GET /api/v1/export/template/{type}
     */
    public function downloadTemplate(Request $request, string $type): \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check permission
        if (!$user->can('import.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing import.create permission'
            ], 403);
        }

        try {
            $response = match($type) {
                'products' => Excel::download(new ProductsExport(['template' => true]), 'template_import_products_' . date('Ymd') . '.xlsx'),
                default => response()->json([
                    'success' => false,
                    'message' => 'Template type not found'
                ], 404)
            };

            // Add CORS headers for file download
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import data dari Excel
     * POST /api/v1/import/{type}
     */
    public function import(Request $request, string $type): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check permission
        if (!$user->can('import.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing import.create permission'
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);

        try {
            $importResult = match($type) {
                'products' => $this->importProducts($request->file('file')),
                default => throw new \Exception('Import type not supported')
            };

            return response()->json([
                'success' => true,
                'message' => 'Import berhasil',
                'data' => $importResult
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
                'errors' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Preview import data (validate sebelum import)
     * POST /api/v1/import/{type}/preview
     */
    public function previewImport(Request $request, string $type): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check permission
        if (!$user->can('import.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing import.create permission'
            ], 403);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $preview = match($type) {
                'products' => $this->previewProductsImport($request->file('file')),
                default => throw new \Exception('Preview type not supported')
            };

            return response()->json([
                'success' => true,
                'data' => $preview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Preview gagal: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Helper: Generate filename untuk export
     */
    private function generateFilename(string $type, array $params, string $extension = 'xlsx'): string
    {
        $prefix = match($type) {
            'products' => 'products_export',
            'sales-report' => 'sales_report',
            'financial-report' => 'financial_report',
            'enhanced-report' => 'enhanced_report',
            'advanced-report' => 'advanced_report',
            default => 'export'
        };

        $dateRange = '';
        if (isset($params['date_from']) && isset($params['date_to'])) {
            $dateRange = '_' . str_replace('-', '', $params['date_from']) . '_to_' . str_replace('-', '', $params['date_to']);
        } elseif (isset($params['outlet_id'])) {
            $dateRange = '_outlet_' . $params['outlet_id'];
        }

        return $prefix . $dateRange . '_' . date('Ymd_His') . '.' . $extension;
    }

    /**
     * Helper: Get data untuk PDF export
     */
    private function getExportData(string $type, array $params): array
    {
        $baseData = [
            'company_name' => \App\Models\Setting::get('company_name', 'Kasir POS'),
            'company_address' => \App\Models\Setting::get('company_address', ''),
            'company_phone' => \App\Models\Setting::get('company_phone', ''),
            'export_date' => now()->format('d/m/Y H:i:s'),
            'params' => $params,
        ];

        // Fetch specific data based on type
        try {
            switch ($type) {
                case 'sales-report':
                    $controller = app(\App\Http\Controllers\Api\ReportController::class);
                    $request = new \Illuminate\Http\Request($params);
                    $response = $controller->sales($request);
                    $responseData = json_decode($response->getContent(), true);
                    return array_merge($baseData, ['data' => $responseData['data'] ?? []]);

                case 'financial-report':
                    $controller = app(\App\Http\Controllers\Api\FinancialReportController::class);
                    $request = new \Illuminate\Http\Request($params);
                    $response = $controller->comprehensive($request);
                    $responseData = json_decode($response->getContent(), true);
                    return array_merge($baseData, ['data' => $responseData['data'] ?? []]);

                case 'enhanced-report':
                    $controller = app(\App\Http\Controllers\Api\EnhancedReportController::class);
                    $request = new \Illuminate\Http\Request($params);
                    $response = $controller->index($request);
                    $responseData = json_decode($response->getContent(), true);
                    return array_merge($baseData, ['data' => $responseData['data'] ?? []]);

                case 'advanced-report':
                    $controller = app(\App\Http\Controllers\Api\AdvancedReportController::class);
                    $request = new \Illuminate\Http\Request($params);
                    $response = $controller->businessIntelligence($request);
                    $responseData = json_decode($response->getContent(), true);
                    return array_merge($baseData, ['data' => $responseData['data'] ?? []]);

                default:
                    return $baseData;
            }
        } catch (\Exception $e) {
            // Return base data if fetch fails
            return $baseData;
        }
    }

    /**
     * Helper: Import products
     */
    private function importProducts($file): array
    {
        $import = new ProductsImport(Auth::user());
        Excel::import($import, $file);

        return [
            'total' => $import->getRowCount(),
            'success' => $import->getSuccessCount(),
            'failed' => $import->getFailedCount(),
            'errors' => $import->getErrors()
        ];
    }

    /**
     * Helper: Preview products import
     */
    private function previewProductsImport($file): array
    {
        $import = new ProductsImport(Auth::user());
        $import->setPreviewMode(true);

        try {
            Excel::import($import, $file);
        } catch (\Exception $e) {
            // Catch validation errors during preview
        }

        return [
            'total_rows' => $import->getRowCount(),
            'valid_rows' => $import->getValidRows(),
            'invalid_rows' => $import->getInvalidRows(),
            'preview_data' => $import->getPreviewData(),
            'errors' => $import->getErrors()
        ];
    }

    /**
     * Helper: Add CORS headers to response
     */
    private function addCorsHeaders($response, Request $request)
    {
        $origin = $request->header('Origin');

        // Allowed origins
        $allowed = [
            'https://kasir-pos.sunnflower.site',
            'http://localhost:4173',
            'http://127.0.0.1:4173',
        ];

        if ($origin && in_array($origin, $allowed)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        }

        return $response;
    }
}

