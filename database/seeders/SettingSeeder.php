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
            ['key' => 'company_logo', 'value' => '', 'type' => 'string'],

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
            ['key' => 'loyalty_points_rate', 'value' => '100', 'type' => 'integer'], // 1 point per 100 rupiah
            ['key' => 'loyalty_redeem_rate', 'value' => '1000', 'type' => 'integer'], // 1000 points = 1000 rupiah

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
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
