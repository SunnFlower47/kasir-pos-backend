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
            // Step 1: Change column to VARCHAR temporarily to allow any value
            DB::statement("ALTER TABLE customers MODIFY COLUMN level VARCHAR(50) DEFAULT 'level1'");

            // Step 2: Migrate existing data from old format to new format
            DB::statement("UPDATE customers SET level = 'level1' WHERE level = 'bronze'");
            DB::statement("UPDATE customers SET level = 'level2' WHERE level = 'silver'");
            DB::statement("UPDATE customers SET level = 'level3' WHERE level = 'gold'");
            DB::statement("UPDATE customers SET level = 'level4' WHERE level = 'platinum'");

            // Step 3: Convert back to ENUM with new values (optional, or keep as VARCHAR for flexibility)
            // Keeping as VARCHAR is more flexible, but if you want ENUM, uncomment below:
            // DB::statement("ALTER TABLE customers MODIFY COLUMN level ENUM('level1', 'level2', 'level3', 'level4') DEFAULT 'level1'");
        } elseif ($driverName === 'sqlite') {
            // SQLite doesn't support ENUM natively, but Laravel creates CHECK constraints
            // We need to recreate the table to remove the constraint
            // Step 1: Create temp table with new structure
            DB::statement("
                CREATE TABLE customers_temp AS
                SELECT
                    id, name, email, phone, address, birth_date, gender,
                    CASE
                        WHEN level = 'bronze' THEN 'level1'
                        WHEN level = 'silver' THEN 'level2'
                        WHEN level = 'gold' THEN 'level3'
                        WHEN level = 'platinum' THEN 'level4'
                        ELSE level
                    END as level,
                    loyalty_points, is_active, created_at, updated_at
                FROM customers
            ");

            // Step 2: Drop old table
            Schema::dropIfExists('customers');

            // Step 3: Recreate table with new structure
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->date('birth_date')->nullable();
                $table->enum('gender', ['male', 'female'])->nullable();
                $table->string('level')->default('level1'); // Use string instead of ENUM for flexibility
                $table->integer('loyalty_points')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Step 4: Copy data back
            DB::statement("
                INSERT INTO customers
                SELECT * FROM customers_temp
            ");

            // Step 5: Drop temp table
            Schema::dropIfExists('customers_temp');
        } elseif ($driverName === 'pgsql') {
            // PostgreSQL - update data first
            DB::statement("UPDATE customers SET level = 'level1' WHERE level = 'bronze'");
            DB::statement("UPDATE customers SET level = 'level2' WHERE level = 'silver'");
            DB::statement("UPDATE customers SET level = 'level3' WHERE level = 'gold'");
            DB::statement("UPDATE customers SET level = 'level4' WHERE level = 'platinum'");

            // PostgreSQL might use ENUM type or VARCHAR
            // Try to alter if it's ENUM, otherwise it's already VARCHAR and no change needed
            try {
                // If using ENUM type, we'd need to drop and recreate, but Laravel typically uses VARCHAR
                // So just ensure default value is correct
                DB::statement("ALTER TABLE customers ALTER COLUMN level SET DEFAULT 'level1'");
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
            // Step 1: Migrate data back to old format
            DB::statement("UPDATE customers SET level = 'bronze' WHERE level = 'level1'");
            DB::statement("UPDATE customers SET level = 'silver' WHERE level = 'level2'");
            DB::statement("UPDATE customers SET level = 'gold' WHERE level = 'level3'");
            DB::statement("UPDATE customers SET level = 'platinum' WHERE level = 'level4'");

            // Step 2: Revert ENUM to old format (if using ENUM, otherwise just set default)
            // DB::statement("ALTER TABLE customers MODIFY COLUMN level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze'");
            DB::statement("ALTER TABLE customers MODIFY COLUMN level VARCHAR(50) DEFAULT 'bronze'");
        } elseif ($driverName === 'sqlite') {
            // SQLite - recreate table to revert constraint
            DB::statement("
                CREATE TABLE customers_temp AS
                SELECT
                    id, name, email, phone, address, birth_date, gender,
                    CASE
                        WHEN level = 'level1' THEN 'bronze'
                        WHEN level = 'level2' THEN 'silver'
                        WHEN level = 'level3' THEN 'gold'
                        WHEN level = 'level4' THEN 'platinum'
                        ELSE level
                    END as level,
                    loyalty_points, is_active, created_at, updated_at
                FROM customers
            ");

            Schema::dropIfExists('customers');

            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->date('birth_date')->nullable();
                $table->enum('gender', ['male', 'female'])->nullable();
                $table->enum('level', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
                $table->integer('loyalty_points')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            DB::statement("
                INSERT INTO customers
                SELECT * FROM customers_temp
            ");

            Schema::dropIfExists('customers_temp');
        } elseif ($driverName === 'pgsql') {
            // PostgreSQL - update data back
            DB::statement("UPDATE customers SET level = 'bronze' WHERE level = 'level1'");
            DB::statement("UPDATE customers SET level = 'silver' WHERE level = 'level2'");
            DB::statement("UPDATE customers SET level = 'gold' WHERE level = 'level3'");
            DB::statement("UPDATE customers SET level = 'platinum' WHERE level = 'level4'");

            try {
                DB::statement("ALTER TABLE customers ALTER COLUMN level SET DEFAULT 'bronze'");
            } catch (\Exception $e) {
                // Ignore errors
            }
        }
    }
};
