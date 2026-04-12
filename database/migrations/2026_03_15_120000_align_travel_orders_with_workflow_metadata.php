<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_orders', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('travel_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('hr_reviewed_at');
            }

            if (!Schema::hasColumn('travel_orders', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('travel_orders', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('travel_orders', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('travel_order_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_order_attachments', 'file_path')) {
                $table->string('file_path')->nullable()->after('path');
            }

            if (!Schema::hasColumn('travel_order_attachments', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->after('label')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('travel_order_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('travel_order_attachments', 'uploaded_by')) {
                $table->dropConstrainedForeignId('uploaded_by');
            }

            if (Schema::hasColumn('travel_order_attachments', 'file_path')) {
                $table->dropColumn('file_path');
            }
        });

        Schema::table('travel_orders', function (Blueprint $table) {
            if (Schema::hasColumn('travel_orders', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('travel_orders', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }

            $drops = [];
            foreach (['submitted_at', 'approved_at', 'rejected_at'] as $column) {
                if (Schema::hasColumn('travel_orders', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
