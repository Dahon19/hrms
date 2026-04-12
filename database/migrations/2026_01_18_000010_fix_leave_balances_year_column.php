<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE leave_balances MODIFY year SMALLINT UNSIGNED');
            DB::statement('UPDATE leave_balances SET year = YEAR(created_at) WHERE year < 100 OR year = 255');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("UPDATE leave_balances SET year = CAST(strftime('%Y', created_at) AS INTEGER) WHERE year < 100 OR year = 255");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE leave_balances MODIFY year TINYINT UNSIGNED');
        }
    }
};
