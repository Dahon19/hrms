<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('positions', 'employee_limit')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->unsignedInteger('employee_limit')->nullable()->after('position');
            });
        }

        $defaults = [
            'head' => 1,
            'dean' => 1,
            'secretary' => 1,
            'coordinator' => 1,
            'staff' => 2,
            'staffs' => 2,
            'instructor' => 15,
        ];

        foreach ($defaults as $position => $limit) {
            DB::table('positions')
                ->whereNull('employee_limit')
                ->whereRaw('LOWER(TRIM(position)) = ?', [$position])
                ->update(['employee_limit' => $limit]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('positions', 'employee_limit')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->dropColumn('employee_limit');
            });
        }
    }
};
