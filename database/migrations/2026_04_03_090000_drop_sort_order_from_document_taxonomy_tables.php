<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('document_categories', 'sort_order')) {
            Schema::table('document_categories', function (Blueprint $table): void {
                $table->dropColumn('sort_order');
            });
        }

        if (Schema::hasColumn('document_subcategories', 'sort_order')) {
            Schema::table('document_subcategories', function (Blueprint $table): void {
                $table->dropColumn('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('document_categories', 'sort_order')) {
            Schema::table('document_categories', function (Blueprint $table): void {
                $table->unsignedSmallInteger('sort_order')->default(0);
            });
        }

        if (! Schema::hasColumn('document_subcategories', 'sort_order')) {
            Schema::table('document_subcategories', function (Blueprint $table): void {
                $table->unsignedSmallInteger('sort_order')->default(0);
            });
        }
    }
};
