<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('department_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('departments')
            ->whereNull('department_type')
            ->update(['department_type' => 'Administrative']);

        Schema::table('departments', function (Blueprint $table) {
            $table->string('department_type')->nullable(false)->change();
        });
    }
};
