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
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out']);

            $table->time('morning_time_in')->nullable()->after('date');
            $table->time('morning_time_out')->nullable()->after('morning_time_in');
            $table->time('afternoon_time_in')->nullable()->after('morning_time_out');
            $table->time('afternoon_time_out')->nullable()->after('afternoon_time_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'morning_time_in',
                'morning_time_out',
                'afternoon_time_in',
                'afternoon_time_out',
            ]);

            $table->time('time_in')->nullable()->after('date');
            $table->time('time_out')->nullable()->after('time_in');
        });
    }
};
