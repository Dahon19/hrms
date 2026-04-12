<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('president_reviewed_by')->nullable()->after('head_reviewed_at');
            $table->timestamp('president_reviewed_at')->nullable()->after('president_reviewed_by');

            $table->foreign('president_reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['president_reviewed_by']);
            $table->dropColumn(['president_reviewed_by', 'president_reviewed_at']);
        });
    }
};
