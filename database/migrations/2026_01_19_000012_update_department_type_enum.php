<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        DB::statement("UPDATE departments SET department_type = 'Academic' WHERE department_type IS NULL OR department_type = ''");
        DB::statement("UPDATE departments SET department_type = 'Administrative' WHERE department_type = 'Aministrative'");
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE departments MODIFY department_type ENUM('Academic','Administrative','Student Services','SupportOperations','Support/Operations') NOT NULL");
        }
        DB::statement("UPDATE departments SET department_type = 'Support/Operations' WHERE department_type IN ('SupportOperations', 'Support Operations')");
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE departments MODIFY department_type ENUM('Academic','Administrative','Student Services','Support/Operations') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE departments MODIFY department_type ENUM('Academic','Administrative','Student Services','Support/Operations') NOT NULL");
        }
    }
};
