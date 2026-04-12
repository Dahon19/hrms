<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_family_backgrounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->string('spouse_last_name')->nullable();
            $table->string('spouse_first_name')->nullable();
            $table->string('spouse_middle_name')->nullable();
            $table->string('spouse_occupation')->nullable();
            $table->string('spouse_employer')->nullable();
            $table->string('spouse_business_address')->nullable();
            $table->string('spouse_telephone')->nullable();
            $table->string('father_last_name')->nullable();
            $table->string('father_first_name')->nullable();
            $table->string('father_middle_name')->nullable();
            $table->string('father_name_extension')->nullable();
            $table->string('mother_last_name')->nullable();
            $table->string('mother_first_name')->nullable();
            $table->string('mother_middle_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('pds_profile_id');
        });

        Schema::create('pds_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->string('full_name');
            $table->date('birth_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pds_profile_id', 'birth_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_children');
        Schema::dropIfExists('pds_family_backgrounds');
    }
};


