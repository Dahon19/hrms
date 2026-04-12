<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offboarding_records')) {
            Schema::table('offboarding_records', function (Blueprint $table) {
                if (!Schema::hasColumn('offboarding_records', 'resignation_reason')) {
                    $table->string('resignation_reason', 150)->nullable()->after('effective_last_working_day');
                }

                if (!Schema::hasColumn('offboarding_records', 'last_working_day')) {
                    $table->date('last_working_day')->nullable()->after('resignation_reason');
                }

                if (!Schema::hasColumn('offboarding_records', 'resignation_letter_attachment')) {
                    $table->string('resignation_letter_attachment')->nullable()->after('last_working_day');
                }

                if (!Schema::hasColumn('offboarding_records', 'initiated_by_hr_id')) {
                    $table->foreignId('initiated_by_hr_id')->nullable()->after('initiated_by')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('offboarding_records', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('status');
                }

                if (!Schema::hasColumn('offboarding_records', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('submitted_at');
                }
            });

            DB::table('offboarding_records')
                ->whereNull('resignation_reason')
                ->update(['resignation_reason' => DB::raw('reason')]);

            DB::table('offboarding_records')
                ->whereNull('last_working_day')
                ->update(['last_working_day' => DB::raw('effective_last_working_day')]);

            DB::table('offboarding_records')
                ->whereNull('initiated_by_hr_id')
                ->update(['initiated_by_hr_id' => DB::raw('initiated_by')]);

            DB::table('offboarding_records')
                ->whereNull('submitted_at')
                ->whereIn('status', ['initiated', 'in_clearance', 'cleared', 'closed'])
                ->update(['submitted_at' => DB::raw('created_at')]);

            DB::table('offboarding_records')
                ->whereNull('completed_at')
                ->whereIn('status', ['cleared', 'closed'])
                ->update(['completed_at' => DB::raw('finalized_at')]);

            DB::table('offboarding_records')->where('status', 'initiated')->update(['status' => 'submitted']);
            DB::table('offboarding_records')->where('status', 'in_clearance')->update(['status' => 'department_review']);
            DB::table('offboarding_records')->where('status', 'cleared')->update(['status' => 'completed']);
            DB::table('offboarding_records')->where('status', 'closed')->update(['status' => 'archived']);
        }

        if (Schema::hasTable('clearance_items')) {
            Schema::table('clearance_items', function (Blueprint $table) {
                if (!Schema::hasColumn('clearance_items', 'approved_by_user_id')) {
                    $table->foreignId('approved_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('clearance_items', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
                }

                if (!Schema::hasColumn('clearance_items', 'notes')) {
                    $table->text('notes')->nullable()->after('remarks');
                }
            });

            DB::table('clearance_items')
                ->whereNull('approved_by_user_id')
                ->update(['approved_by_user_id' => DB::raw('cleared_by')]);

            DB::table('clearance_items')
                ->whereNull('approved_at')
                ->update(['approved_at' => DB::raw('cleared_at')]);

            DB::table('clearance_items')
                ->whereNull('notes')
                ->update(['notes' => DB::raw('remarks')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clearance_items')) {
            Schema::table('clearance_items', function (Blueprint $table) {
                if (Schema::hasColumn('clearance_items', 'approved_by_user_id')) {
                    $table->dropConstrainedForeignId('approved_by_user_id');
                }

                if (Schema::hasColumn('clearance_items', 'approved_at')) {
                    $table->dropColumn('approved_at');
                }

                if (Schema::hasColumn('clearance_items', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }

        if (Schema::hasTable('offboarding_records')) {
            DB::table('offboarding_records')->where('status', 'submitted')->update(['status' => 'initiated']);
            DB::table('offboarding_records')->where('status', 'department_review')->update(['status' => 'in_clearance']);
            DB::table('offboarding_records')->where('status', 'completed')->update(['status' => 'cleared']);
            DB::table('offboarding_records')->where('status', 'archived')->update(['status' => 'closed']);

            Schema::table('offboarding_records', function (Blueprint $table) {
                if (Schema::hasColumn('offboarding_records', 'initiated_by_hr_id')) {
                    $table->dropConstrainedForeignId('initiated_by_hr_id');
                }

                foreach ([
                    'resignation_reason',
                    'last_working_day',
                    'resignation_letter_attachment',
                    'submitted_at',
                    'completed_at',
                ] as $column) {
                    if (Schema::hasColumn('offboarding_records', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
