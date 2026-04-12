<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'rfid')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('rfid')->nullable()->after('employee_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employees', 'rfid')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('rfid');
            });
        }
    }
};
