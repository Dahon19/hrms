<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offboarding_record_id')->constrained()->cascadeOnDelete();
            $table->string('unit_name', 120);
            $table->string('owner_role', 60);
            $table->string('module_key', 60)->nullable();
            $table->string('item_name', 160);
            $table->string('status', 20)->default('pending');
            $table->boolean('required')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['offboarding_record_id', 'item_name']);
            $table->index(['offboarding_record_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_items');
    }
};

