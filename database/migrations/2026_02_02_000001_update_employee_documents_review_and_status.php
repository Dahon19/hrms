<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_documents', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('status');
            }
        });

        DB::statement("UPDATE employee_documents SET status = 'submitted' WHERE status IN ('active','archived')");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employee_documents MODIFY status ENUM('submitted','verified','reupload') NOT NULL DEFAULT 'submitted'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE employee_documents MODIFY status ENUM('active','archived') NOT NULL DEFAULT 'active'");
        }
        Schema::table('employee_documents', function (Blueprint $table) {
            if (Schema::hasColumn('employee_documents', 'review_notes')) {
                $table->dropColumn('review_notes');
            }
        });
    }
};
