<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Company Information
            ['key' => 'company_name', 'value' => 'Kasir POS System', 'type' => 'string'],
            ['key' => 'company_address', 'value' => 'Jl. Sudirman No. 123, Jakarta Pusat', 'type' => 'string'],
            ['key' => 'company_phone', 'value' => '021-12345678', 'type' => 'string'],
            ['key' => 'company_email', 'value' => 'info@kasirpos.com', 'type' => 'string'],
            ['key' => 'company_website', 'value' => '', 'type' => 'string'],
            ['key' => 'company_npwp', 'value' => '', 'type' => 'string'],
            ['key' => 'company_logo', 'value' => '', 'type' => 'string'],

            // Application Settings
            ['key' => 'app_name', 'value' => 'Kasir POS', 'type' => 'string'],
            ['key' => 'app_logo', 'value' => '', 'type' => 'string'],

            // Receipt Settings
            ['key' => 'receipt_header', 'value' => 'Terima kasih telah berbelanja', 'type' => 'string'],
            ['key' => 'receipt_footer', 'value' => 'Barang yang sudah dibeli tidak dapat dikembalikan', 'type' => 'string'],
            ['key' => 'receipt_show_logo', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'receipt_paper_size', 'value' => '80mm', 'type' => 'string'],

            // Tax Settings
            ['key' => 'tax_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'tax_rate', 'value' => '10', 'type' => 'integer'],
            ['key' => 'tax_name', 'value' => 'PPN', 'type' => 'string'],

            // Currency Settings
            ['key' => 'currency_symbol', 'value' => 'Rp', 'type' => 'string'],
            ['key' => 'currency_position', 'value' => 'before', 'type' => 'string'],
            ['key' => 'decimal_places', 'value' => '0', 'type' => 'integer'],

            // Loyalty Settings
            ['key' => 'loyalty_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'loyalty_points_per_rupiah', 'value' => '200', 'type' => 'integer'], // Berapa rupiah untuk 1 point (default: 200 rupiah = 1 point)
            ['key' => 'loyalty_redeem_rate', 'value' => '1000', 'type' => 'integer'], // 1000 points = 1000 rupiah

            // Custom Level Names
            ['key' => 'loyalty_level1_name', 'value' => 'Bronze', 'type' => 'string'],
            ['key' => 'loyalty_level2_name', 'value' => 'Silver', 'type' => 'string'],
            ['key' => 'loyalty_level3_name', 'value' => 'Gold', 'type' => 'string'],
            ['key' => 'loyalty_level4_name', 'value' => 'Platinum', 'type' => 'string'],

            // Level Thresholds (range lebih tinggi)
            ['key' => 'loyalty_level1_max', 'value' => '4999', 'type' => 'integer'], // Level 1: 0-4999 points
            ['key' => 'loyalty_level2_min', 'value' => '5000', 'type' => 'integer'], // Level 2: 5000-24999 points
            ['key' => 'loyalty_level2_max', 'value' => '24999', 'type' => 'integer'],
            ['key' => 'loyalty_level3_min', 'value' => '25000', 'type' => 'integer'], // Level 3: 25000-99999 points
            ['key' => 'loyalty_level3_max', 'value' => '99999', 'type' => 'integer'],
            ['key' => 'loyalty_level4_min', 'value' => '100000', 'type' => 'integer'], // Level 4: 100000+ points

            // Backward compatibility - keep old keys for migration
            ['key' => 'loyalty_points_rate', 'value' => '200', 'type' => 'integer'], // Deprecated, use loyalty_points_per_rupiah

            // Refund Settings
            ['key' => 'refund_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'refund_days_limit', 'value' => '7', 'type' => 'integer'], // Batasan hari untuk refund (0 = tanpa batasan)
            ['key' => 'refund_allow_same_day_only_for_cashier', 'value' => '1', 'type' => 'boolean'], // Kasir hanya bisa refund hari ini

            // Stock Settings
            ['key' => 'low_stock_alert', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'auto_reduce_stock', 'value' => '1', 'type' => 'boolean'],

            // System Settings
            ['key' => 'timezone', 'value' => 'Asia/Jakarta', 'type' => 'string'],
            ['key' => 'date_format', 'value' => 'd/m/Y', 'type' => 'string'],
            ['key' => 'time_format', 'value' => 'H:i', 'type' => 'string'],

            // Backup Settings
            ['key' => 'auto_backup', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'backup_frequency', 'value' => 'daily', 'type' => 'string'],

            // Printer Settings
            ['key' => 'printer_name', 'value' => 'Default Printer', 'type' => 'string'],
            ['key' => 'printer_type', 'value' => 'thermal', 'type' => 'string'],
            ['key' => 'paper_size', 'value' => '58mm', 'type' => 'string'],
            ['key' => 'print_logo', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'print_header', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'print_footer', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'font_size', 'value' => 'normal', 'type' => 'string'],
            ['key' => 'print_copies', 'value' => '1', 'type' => 'integer'],
            ['key' => 'auto_print', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'print_customer_copy', 'value' => '0', 'type' => 'boolean'],

            // Notification Settings
            ['key' => 'email_notifications', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'sms_notifications', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'low_stock_threshold', 'value' => '10', 'type' => 'integer'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type']
                ]
            );
        }
    }
}
