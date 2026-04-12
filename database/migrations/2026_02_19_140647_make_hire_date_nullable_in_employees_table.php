<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'hire_date')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE employees MODIFY hire_date DATE NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'hire_date')) {
            return;
        }

        // Backfill null/zero/invalid values before restoring NOT NULL constraint.
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                UPDATE employees
                SET hire_date = COALESCE(
                    NULLIF(hire_date, '0000-00-00'),
                    DATE(created_at),
                    CURDATE()
                )
                WHERE hire_date IS NULL OR hire_date = '0000-00-00'
            ");
            DB::statement('ALTER TABLE employees MODIFY hire_date DATE NOT NULL');
        } elseif ($driver === 'sqlite') {
            DB::statement("
                UPDATE employees
                SET hire_date = COALESCE(hire_date, DATE(created_at), DATE('now'))
                WHERE hire_date IS NULL OR hire_date = '0000-00-00'
            ");
        }
    }
};
