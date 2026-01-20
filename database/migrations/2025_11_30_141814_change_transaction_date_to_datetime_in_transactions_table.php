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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN to change type directly
            // We'll add a new datetime column, copy data, then drop the old one
            Schema::table('transactions', function (Blueprint $table) {
                $table->dateTime('transaction_date_tmp')->nullable()->after('user_id');
            });

            // Copy existing date data and add default time 00:00:00
            // Then convert to datetime format
            DB::statement("
                UPDATE transactions
                SET transaction_date_tmp = datetime(transaction_date || ' 00:00:00')
                WHERE transaction_date IS NOT NULL
            ");

            // Drop old column
            Schema::table('transactions', function (Blueprint $table) {
                // Drop indexes that use this column first to prevent SQLite errors
                $table->dropIndex(['transaction_date']); 
                $table->dropIndex(['outlet_id', 'transaction_date']);
                
                $table->dropColumn('transaction_date');
            });

            // Add new column with correct name
            Schema::table('transactions', function (Blueprint $table) {
                $table->dateTime('transaction_date')->nullable()->after('user_id');
            });

            // Copy from temp column
            DB::statement("
                UPDATE transactions
                SET transaction_date = transaction_date_tmp
            ");

            // Drop temp column
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('transaction_date_tmp');
            });
        } else {
            // For MySQL/PostgreSQL, we can use change()
            Schema::table('transactions', function (Blueprint $table) {
                $table->dateTime('transaction_date')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Reverse: convert back to date
            Schema::table('transactions', function (Blueprint $table) {
                $table->date('transaction_date_tmp')->nullable()->after('user_id');
            });

            DB::statement("
                UPDATE transactions
                SET transaction_date_tmp = date(transaction_date)
                WHERE transaction_date IS NOT NULL
            ");

            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('transaction_date');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->date('transaction_date')->nullable()->after('user_id');
            });

            DB::statement("
                UPDATE transactions
                SET transaction_date = transaction_date_tmp
            ");

            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('transaction_date_tmp');
            });
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->date('transaction_date')->nullable()->change();
            });
        }
    }
};
