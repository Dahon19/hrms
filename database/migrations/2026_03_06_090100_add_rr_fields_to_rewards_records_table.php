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
        Schema::table('rewards_records', function (Blueprint $table) {
            $table->string('milestone_type', 50)->nullable()->after('award_type');
            $table->string('eligibility_reference', 100)->nullable()->after('milestone_type');
            $table->foreignId('assigned_by')->nullable()->after('remarks')->constrained('users')->nullOnDelete();
            $table->boolean('override_used')->default(false)->after('assigned_by');
            $table->text('override_reason')->nullable()->after('override_used');

            $table->index('milestone_type', 'rewards_milestone_type_index');
            $table->index('override_used', 'rewards_override_used_index');
            $table->index('assigned_by', 'rewards_assigned_by_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rewards_records', function (Blueprint $table) {
            $table->dropIndex('rewards_milestone_type_index');
            $table->dropIndex('rewards_override_used_index');
            $table->dropIndex('rewards_assigned_by_index');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn([
                'milestone_type',
                'eligibility_reference',
                'override_used',
                'override_reason',
            ]);
        });
    }
};

