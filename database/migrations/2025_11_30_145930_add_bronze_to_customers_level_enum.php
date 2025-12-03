<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check database driver
        $driverName = DB::connection()->getDriverName();

        if ($driverName === 'mysql') {
            // For MySQL, modify the ENUM column
            DB::statement("ALTER TABLE customers MODIFY COLUMN level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze'");
        } elseif ($driverName === 'sqlite') {
            // SQLite doesn't support ENUM, so if using SQLite, the constraint is only in Laravel validation
            // No database changes needed for SQLite - just ensure validation accepts 'bronze'
        } elseif ($driverName === 'pgsql') {
            // For PostgreSQL, if using ENUM type, we need to modify it
            // But since Laravel uses string columns by default, this might not be needed
            // Check if column exists and is ENUM type first
            try {
                DB::statement("ALTER TABLE customers ALTER COLUMN level TYPE VARCHAR(255)");
                DB::statement("ALTER TABLE customers ALTER COLUMN level SET DEFAULT 'bronze'");
            } catch (\Exception $e) {
                // Column might already be VARCHAR or migration might not be needed
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driverName = DB::connection()->getDriverName();

        if ($driverName === 'mysql') {
            // Revert to original ENUM values (but keep existing bronze data)
            // Note: This will fail if there are customers with bronze level
            DB::statement("ALTER TABLE customers MODIFY COLUMN level ENUM('silver', 'gold', 'platinum') DEFAULT 'silver'");
        }
        // SQLite and PostgreSQL don't need special handling for rollback
    }
};
