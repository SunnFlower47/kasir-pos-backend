<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{
    /**
     * Generate receipt PDF for transaction
     */
    public function generatePdf(Transaction $transaction)
    {
        try {
            $transaction->load(['customer', 'outlet', 'user', 'transactionItems.product.category', 'transactionItems.product.unit', 'transactionItems.unit']);

            // Get company settings with fallbacks
            $companyName = Setting::get('company_name', 'Kasir POS System');
            $companyAddress = Setting::get('company_address', '');
            $companyPhone = Setting::get('company_phone', '');
            $receiptHeader = Setting::get('receipt_header', 'Terima kasih telah berbelanja');
            $receiptFooter = Setting::get('receipt_footer', 'Barang yang sudah dibeli tidak dapat dikembalikan');
            $currencySymbol = Setting::get('currency_symbol', 'Rp');
            $taxEnabled = Setting::get('tax_enabled', true);
            $taxRate = Setting::get('tax_rate', 10);

            $data = [
                'transaction' => $transaction,
                'company' => [
                    'name' => $companyName,
                    'address' => $companyAddress,
                    'phone' => $companyPhone,
                ],
                'receipt' => [
                    'header' => $receiptHeader,
                    'footer' => $receiptFooter,
                ],
                'currency_symbol' => $currencySymbol,
                'tax_enabled' => $taxEnabled,
                'tax_rate' => $taxRate,
            ];

            $pdf = Pdf::loadView('receipts.transaction', $data);
            // Optimized for 92-94% scaling: slightly wider than 58mm
            $pdf->setPaper([0, 0, 180, 841.89], 'portrait'); // 180pt ≈ 63.5mm for 92-94% scaling

        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Failed to generate PDF receipt. Please try again later.'
                    : 'Failed to generate PDF receipt: ' . $e->getMessage()
            ], 500);
        }

        return $pdf->stream("receipt-{$transaction->transaction_number}.pdf");
    }

    /**
     * Generate simple receipt PDF for transaction (better for 58mm)
     */
    public function generateSimplePdf(Transaction $transaction)
    {
        try {
            $transaction->load(['customer', 'outlet', 'user', 'transactionItems.product.category', 'transactionItems.product.unit', 'transactionItems.unit']);

            // Get company settings with fallbacks
            // Priority: Outlet data > Global settings > Default values
            // Header/Footer: Always from global settings (same for all outlets)
            $companyName = $transaction->outlet?->name ?? Setting::get('company_name', 'Kasir POS System');
            $companyAddress = $transaction->outlet?->address ?? Setting::get('company_address', '');
            $companyPhone = $transaction->outlet?->phone ?? Setting::get('company_phone', '');
            $receiptHeader = Setting::get('receipt_header', 'Terima kasih telah berbelanja');
            $receiptFooter = Setting::get('receipt_footer', 'Barang yang sudah dibeli tidak dapat dikembalikan');
            $currencySymbol = Setting::get('currency_symbol', 'Rp');
            $taxEnabled = Setting::get('tax_enabled', true);
            $taxRate = Setting::get('tax_rate', 10);

            $data = [
                'transaction' => $transaction,
                'company' => [
                    'name' => $companyName,
                    'address' => $companyAddress,
                    'phone' => $companyPhone,
                ],
                'receipt' => [
                    'header' => $receiptHeader,
                    'footer' => $receiptFooter,
                ],
                'currency_symbol' => $currencySymbol,
                'tax_enabled' => $taxEnabled,
                'tax_rate' => $taxRate,
            ];

            $pdf = Pdf::loadView('receipts.transaction-simple', $data);
            // Optimized for 92-94% scaling: slightly wider than 58mm
            $pdf->setPaper([0, 0, 180, 841.89], 'portrait'); // 180pt ≈ 63.5mm for 92-94% scaling

        } catch (\Exception $e) {
            Log::error('Simple PDF Generation Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Failed to generate PDF receipt. Please try again later.'
                    : 'Failed to generate simple PDF receipt: ' . $e->getMessage()
            ], 500);
        }

        return $pdf->stream("receipt-simple-{$transaction->transaction_number}.pdf");
    }

    /**
     * Generate 58mm optimized receipt PDF (for 92-94% scaling)
     */
    public function generate58mmPdf(Transaction $transaction)
    {
        try {
            $transaction->load(['customer', 'outlet', 'user', 'transactionItems.product.category', 'transactionItems.product.unit', 'transactionItems.unit']);

            // Get company settings with fallbacks
            // Priority: Outlet data > Global settings > Default values
            // Header/Footer: Always from global settings (same for all outlets)
            $companyName = $transaction->outlet?->name ?? Setting::get('company_name', 'Kasir POS System');
            $companyAddress = $transaction->outlet?->address ?? Setting::get('company_address', '');
            $companyPhone = $transaction->outlet?->phone ?? Setting::get('company_phone', '');
            $receiptHeader = Setting::get('receipt_header', 'Terima kasih telah berbelanja');
            $receiptFooter = Setting::get('receipt_footer', 'Barang yang sudah dibeli tidak dapat dikembalikan');
            $currencySymbol = Setting::get('currency_symbol', 'Rp');
            $taxEnabled = Setting::get('tax_enabled', true);
            $taxRate = Setting::get('tax_rate', 10);

            $data = [
                'transaction' => $transaction,
                'company' => [
                    'name' => $companyName,
                    'address' => $companyAddress,
                    'phone' => $companyPhone,
                ],
                'receipt' => [
                    'header' => $receiptHeader,
                    'footer' => $receiptFooter,
                ],
                'currency_symbol' => $currencySymbol,
                'tax_enabled' => $taxEnabled,
                'tax_rate' => $taxRate,
            ];

            $pdf = Pdf::loadView('receipts.transaction-58mm', $data);
            // Exact 58mm width - optimized for 92-94% browser scaling
            $pdf->setPaper([0, 0, 164.41, 841.89], 'portrait'); // 58mm = 164.41 points

        } catch (\Exception $e) {
            Log::error('58mm PDF Generation Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => app()->environment('production')
                    ? 'Failed to generate PDF receipt. Please try again later.'
                    : 'Failed to generate 58mm PDF receipt: ' . $e->getMessage()
            ], 500);
        }

        return $pdf->stream("receipt-58mm-{$transaction->transaction_number}.pdf");
    }

    /**
     * Generate receipt HTML for preview
     */
    public function generateHtml(Transaction $transaction): JsonResponse
    {
        $transaction->load(['customer', 'outlet', 'user', 'transactionItems.product.category', 'transactionItems.product.unit', 'transactionItems.unit']);

        // Get company settings
        // Priority: Outlet data > Global settings > Default values
        // Header/Footer: Always from global settings (same for all outlets)
        $companyName = $transaction->outlet?->name ?? Setting::get('company_name', 'Kasir POS System');
        $companyAddress = $transaction->outlet?->address ?? Setting::get('company_address', '');
        $companyPhone = $transaction->outlet?->phone ?? Setting::get('company_phone', '');
        $receiptHeader = Setting::get('receipt_header', 'Terima kasih telah berbelanja');
        $receiptFooter = Setting::get('receipt_footer', 'Barang yang sudah dibeli tidak dapat dikembalikan');
        $currencySymbol = Setting::get('currency_symbol', 'Rp');
        $taxEnabled = Setting::get('tax_enabled', true);
        $taxRate = Setting::get('tax_rate', 10);

        $data = [
            'transaction' => $transaction,
            'company' => [
                'name' => $companyName,
                'address' => $companyAddress,
                'phone' => $companyPhone,
            ],
            'receipt' => [
                'header' => $receiptHeader,
                'footer' => $receiptFooter,
            ],
            'currency_symbol' => $currencySymbol,
            'tax_enabled' => $taxEnabled,
            'tax_rate' => $taxRate,
        ];

        $html = view('receipts.transaction', $data)->render();

        return response()->json([
            'success' => true,
            'data' => [
                'html' => $html,
                'transaction_number' => $transaction->transaction_number,
            ]
        ]);
    }

    /**
     * Get receipt settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = [
            'company_name' => Setting::get('company_name', 'Kasir POS System'),
            'company_address' => Setting::get('company_address', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_email' => Setting::get('company_email', ''),
            'receipt_header' => Setting::get('receipt_header', 'Terima kasih telah berbelanja'),
            'receipt_footer' => Setting::get('receipt_footer', 'Barang yang sudah dibeli tidak dapat dikembalikan'),
            'receipt_show_logo' => Setting::get('receipt_show_logo', true),
            'receipt_paper_size' => Setting::get('receipt_paper_size', '80mm'),
            'currency_symbol' => Setting::get('currency_symbol', 'Rp'),
            'currency_position' => Setting::get('currency_position', 'before'),
            'decimal_places' => Setting::get('decimal_places', 0),
            'tax_enabled' => Setting::get('tax_enabled', true),
            'tax_rate' => Setting::get('tax_rate', 10),
            'tax_name' => Setting::get('tax_name', 'PPN'),
        ];

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update receipt settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email',
            'receipt_header' => 'nullable|string',
            'receipt_footer' => 'nullable|string',
            'receipt_show_logo' => 'boolean',
            'receipt_paper_size' => 'nullable|in:58mm,80mm,A4',
            'currency_symbol' => 'nullable|string|max:10',
            'currency_position' => 'nullable|in:before,after',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'tax_enabled' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_name' => 'nullable|string|max:10',
        ]);

        foreach ($request->all() as $key => $value) {
            if ($value !== null) {
                $type = match ($key) {
                    'receipt_show_logo', 'tax_enabled' => 'boolean',
                    'decimal_places', 'tax_rate' => 'integer',
                    default => 'string',
                };
                Setting::set($key, $value, $type);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Receipt settings updated successfully'
        ]);
    }
}
