<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE employee_documents SET status = 'submitted' WHERE status = 'active'");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employee_documents MODIFY status ENUM('submitted','archived') NOT NULL DEFAULT 'submitted'");
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE employee_documents SET status = 'active' WHERE status = 'submitted'");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employee_documents MODIFY status ENUM('active','archived') NOT NULL DEFAULT 'active'");
        }
    }
};
