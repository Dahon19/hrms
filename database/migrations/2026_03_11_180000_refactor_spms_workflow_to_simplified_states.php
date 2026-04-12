<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildForSqlite();
        } else {
            Schema::table('spms_cycles', function (Blueprint $table) {
                if (!Schema::hasColumn('spms_cycles', 'ready_for_closure_at')) {
                    $table->timestamp('ready_for_closure_at')->nullable()->after('status');
                }
            });

            Schema::table('spms_evaluations', function (Blueprint $table) {
                if (!Schema::hasColumn('spms_evaluations', 'rating_label')) {
                    $table->string('rating_label', 32)->nullable()->after('total_score');
                }
            });

            DB::table('spms_cycles')->where('status', 'draft')->update(['status' => 'setup']);
            DB::table('spms_cycles')->whereIn('status', ['submitted', 'reviewed'])->update(['status' => 'evaluation']);
            DB::table('spms_cycles')->where('status', 'locked')->update(['status' => 'closed']);

            DB::table('spms_evaluations')->where('status', 'draft')->update(['status' => 'pending']);
            DB::table('spms_evaluations')->whereIn('status', ['submitted', 'reviewed', 'verified'])->update(['status' => 'submitted']);
            DB::table('spms_evaluations')->where('status', 'locked')->update(['status' => 'final']);

            DB::statement("ALTER TABLE spms_cycles MODIFY status ENUM('setup','evaluation','closed') NOT NULL DEFAULT 'setup'");
            DB::statement("ALTER TABLE spms_evaluations MODIFY status ENUM('pending','submitted','final') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->restoreForSqlite();
        } else {
            DB::table('spms_cycles')->where('status', 'setup')->update(['status' => 'draft']);
            DB::table('spms_cycles')->where('status', 'evaluation')->update(['status' => 'submitted']);
            DB::table('spms_cycles')->where('status', 'closed')->update(['status' => 'locked']);

            DB::table('spms_evaluations')->where('status', 'pending')->update(['status' => 'draft']);
            DB::table('spms_evaluations')->where('status', 'submitted')->update(['status' => 'reviewed']);
            DB::table('spms_evaluations')->where('status', 'final')->update(['status' => 'locked']);

            DB::statement("ALTER TABLE spms_cycles MODIFY status ENUM('draft','submitted','reviewed','locked') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE spms_evaluations MODIFY status ENUM('draft','submitted','reviewed','locked') NOT NULL DEFAULT 'draft'");
            Schema::table('spms_evaluations', function (Blueprint $table) {
                if (Schema::hasColumn('spms_evaluations', 'rating_label')) {
                    $table->dropColumn('rating_label');
                }
            });

            Schema::table('spms_cycles', function (Blueprint $table) {
                if (Schema::hasColumn('spms_cycles', 'ready_for_closure_at')) {
                    $table->dropColumn('ready_for_closure_at');
                }
            });
        }
    }

    private function rebuildForSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('spms_cycles_new', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('setup');
            $table->timestamp('ready_for_closure_at')->nullable();
            $table->timestamps();
            $table->index(['period_start', 'period_end'], 'spms_cycles_period_index_new');
            $table->index('status', 'spms_cycles_status_index_new');
        });

        DB::statement("
            INSERT INTO spms_cycles_new (id, title, period_start, period_end, status, ready_for_closure_at, created_at, updated_at)
            SELECT id, title, period_start, period_end,
                CASE
                    WHEN status = 'draft' THEN 'setup'
                    WHEN status IN ('submitted', 'reviewed', 'verified') THEN 'evaluation'
                    WHEN status = 'locked' THEN 'closed'
                    ELSE status
                END,
                NULL,
                created_at,
                updated_at
            FROM spms_cycles
        ");

        Schema::drop('spms_cycles');
        Schema::rename('spms_cycles_new', 'spms_cycles');

        Schema::create('spms_evaluations_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('spms_cycles')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('total_score', 7, 2)->default(0);
            $table->string('rating_label', 32)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'cycle_id'], 'spms_eval_employee_cycle_unique_new');
            $table->index(['cycle_id', 'status'], 'spms_eval_cycle_status_index_new');
            $table->index(['evaluator_id', 'status'], 'spms_eval_evaluator_status_index_new');
        });

        DB::statement("
            INSERT INTO spms_evaluations_new (id, employee_id, cycle_id, evaluator_id, status, total_score, rating_label, created_at, updated_at)
            SELECT id, employee_id, cycle_id, evaluator_id,
                CASE
                    WHEN status = 'draft' THEN 'pending'
                    WHEN status IN ('submitted', 'reviewed', 'verified') THEN 'submitted'
                    WHEN status = 'locked' THEN 'final'
                    ELSE status
                END,
                total_score,
                NULL,
                created_at,
                updated_at
            FROM spms_evaluations
        ");

        Schema::drop('spms_evaluations');
        Schema::rename('spms_evaluations_new', 'spms_evaluations');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function restoreForSqlite(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::create('spms_cycles_old', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->index(['period_start', 'period_end'], 'spms_cycles_period_index_old');
            $table->index('status', 'spms_cycles_status_index_old');
        });

        DB::statement("
            INSERT INTO spms_cycles_old (id, title, period_start, period_end, status, created_at, updated_at)
            SELECT id, title, period_start, period_end,
                CASE
                    WHEN status = 'setup' THEN 'draft'
                    WHEN status = 'evaluation' THEN 'submitted'
                    WHEN status = 'closed' THEN 'locked'
                    ELSE status
                END,
                created_at,
                updated_at
            FROM spms_cycles
        ");

        Schema::drop('spms_cycles');
        Schema::rename('spms_cycles_old', 'spms_cycles');

        Schema::create('spms_evaluations_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('spms_cycles')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->decimal('total_score', 7, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'cycle_id'], 'spms_eval_employee_cycle_unique_old');
            $table->index(['cycle_id', 'status'], 'spms_eval_cycle_status_index_old');
            $table->index(['evaluator_id', 'status'], 'spms_eval_evaluator_status_index_old');
        });

        DB::statement("
            INSERT INTO spms_evaluations_old (id, employee_id, cycle_id, evaluator_id, status, total_score, created_at, updated_at)
            SELECT id, employee_id, cycle_id, evaluator_id,
                CASE
                    WHEN status = 'pending' THEN 'draft'
                    WHEN status = 'submitted' THEN 'reviewed'
                    WHEN status = 'final' THEN 'locked'
                    ELSE status
                END,
                total_score,
                created_at,
                updated_at
            FROM spms_evaluations
        ");

        Schema::drop('spms_evaluations');
        Schema::rename('spms_evaluations_old', 'spms_evaluations');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
