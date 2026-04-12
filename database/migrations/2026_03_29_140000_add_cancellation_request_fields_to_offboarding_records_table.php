<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('offboarding_records')) {
            return;
        }

        Schema::table('offboarding_records', function (Blueprint $table) {
            if (!Schema::hasColumn('offboarding_records', 'cancellation_requested_by')) {
                $table->foreignId('cancellation_requested_by')->nullable()->after('finalized_by')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('offboarding_records', 'cancellation_reviewed_by')) {
                $table->foreignId('cancellation_reviewed_by')->nullable()->after('cancellation_requested_by')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('offboarding_records', 'cancellation_requested_at')) {
                $table->timestamp('cancellation_requested_at')->nullable()->after('reopened_at');
            }

            if (!Schema::hasColumn('offboarding_records', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancellation_requested_at');
            }

            if (!Schema::hasColumn('offboarding_records', 'cancellation_request_status')) {
                $table->string('cancellation_request_status', 30)->nullable()->after('cancellation_reason');
            }

            if (!Schema::hasColumn('offboarding_records', 'cancellation_reviewed_at')) {
                $table->timestamp('cancellation_reviewed_at')->nullable()->after('cancellation_request_status');
            }

            if (!Schema::hasColumn('offboarding_records', 'cancellation_review_notes')) {
                $table->text('cancellation_review_notes')->nullable()->after('cancellation_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('offboarding_records')) {
            return;
        }

        Schema::table('offboarding_records', function (Blueprint $table) {
            foreach ([
                'cancellation_review_notes',
                'cancellation_reviewed_at',
                'cancellation_request_status',
                'cancellation_reason',
                'cancellation_requested_at',
            ] as $column) {
                if (Schema::hasColumn('offboarding_records', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('offboarding_records', 'cancellation_reviewed_by')) {
                $table->dropConstrainedForeignId('cancellation_reviewed_by');
            }

            if (Schema::hasColumn('offboarding_records', 'cancellation_requested_by')) {
                $table->dropConstrainedForeignId('cancellation_requested_by');
            }
        });
    }
};
