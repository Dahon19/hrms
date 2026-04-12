<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_orders', 'budget_proposal')) {
                $table->decimal('budget_proposal', 12, 2)->nullable()->after('transport_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('travel_orders', function (Blueprint $table) {
            if (Schema::hasColumn('travel_orders', 'budget_proposal')) {
                $table->dropColumn('budget_proposal');
            }
        });
    }
};
