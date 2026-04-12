<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('travel_orders')) {
            Schema::table('travel_orders', function (Blueprint $table) {
                $table->index(['status', 'updated_at'], 'travel_orders_status_updated_idx');
                $table->index(['status', 'department_id', 'updated_at'], 'travel_orders_status_dept_updated_idx');
            });
        }

        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->index(['status', 'president_reviewed_by'], 'leave_requests_status_president_idx');
                $table->index(['status', 'start_date', 'end_date'], 'leave_requests_status_dates_idx');
            });
        }

        if (Schema::hasTable('offboarding_records')) {
            Schema::table('offboarding_records', function (Blueprint $table) {
                $table->index(['employee_id', 'status'], 'offboarding_records_employee_status_idx');
                $table->index(['status', 'cancellation_request_status'], 'offboarding_records_status_cancellation_idx');
            });
        }

        if (Schema::hasTable('clearance_items')) {
            Schema::table('clearance_items', function (Blueprint $table) {
                $table->index(['offboarding_record_id', 'owner_role', 'status'], 'clearance_items_record_owner_status_idx');
                $table->index(['owner_role', 'status'], 'clearance_items_owner_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clearance_items')) {
            Schema::table('clearance_items', function (Blueprint $table) {
                $table->dropIndex('clearance_items_record_owner_status_idx');
                $table->dropIndex('clearance_items_owner_status_idx');
            });
        }

        if (Schema::hasTable('offboarding_records')) {
            Schema::table('offboarding_records', function (Blueprint $table) {
                $table->dropIndex('offboarding_records_employee_status_idx');
                $table->dropIndex('offboarding_records_status_cancellation_idx');
            });
        }

        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropIndex('leave_requests_status_president_idx');
                $table->dropIndex('leave_requests_status_dates_idx');
            });
        }

        if (Schema::hasTable('travel_orders')) {
            Schema::table('travel_orders', function (Blueprint $table) {
                $table->dropIndex('travel_orders_status_updated_idx');
                $table->dropIndex('travel_orders_status_dept_updated_idx');
            });
        }
    }
};
