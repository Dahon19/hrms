<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_personal_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_profile_id')->constrained('pds_profiles')->cascadeOnDelete();
            $table->string('last_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('name_extension')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('sex', 20)->nullable();
            $table->string('civil_status', 50)->nullable();
            $table->string('citizenship', 120)->nullable();
            $table->decimal('height_m', 4, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('blood_type', 10)->nullable();
            $table->string('gsis_no', 40)->nullable();
            $table->string('sss_no', 40)->nullable();
            $table->string('tin_no', 40)->nullable();
            $table->string('philhealth_no', 40)->nullable();
            $table->text('residential_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('telephone_no', 50)->nullable();
            $table->string('mobile_no', 50)->nullable();
            $table->string('email_address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('pds_profile_id');
            $table->index('birth_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_personal_infos');
    }
};


