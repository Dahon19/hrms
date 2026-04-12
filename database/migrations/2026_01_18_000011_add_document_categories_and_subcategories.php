<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_category_id')->constrained('document_categories')->onDelete('cascade');
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['document_category_id', 'name']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('document_category_id')
                ->nullable()
                ->after('id')
                ->constrained('document_categories')
                ->nullOnDelete();
            $table->foreignId('document_subcategory_id')
                ->nullable()
                ->after('document_category_id')
                ->constrained('document_subcategories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_category_id']);
            $table->dropForeign(['document_subcategory_id']);
            $table->dropColumn(['document_category_id', 'document_subcategory_id']);
        });

        Schema::dropIfExists('document_subcategories');
        Schema::dropIfExists('document_categories');
    }
};
