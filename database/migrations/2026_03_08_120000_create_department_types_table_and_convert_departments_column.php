<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function normalizeDepartmentTypeColumn(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE departments MODIFY department_type VARCHAR(255) NOT NULL");
    }

    public function up(): void
    {
        Schema::create('department_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->normalizeDepartmentTypeColumn();

        $types = DB::table('departments')
            ->select('department_type')
            ->distinct()
            ->pluck('department_type')
            ->filter()
            ->map(fn ($type) => trim((string) $type))
            ->unique()
            ->values();

        $now = now();
        foreach ($types as $index => $type) {
            DB::table('department_types')->updateOrInsert(
                ['name' => $type],
                ['sort_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_types');
        $this->normalizeDepartmentTypeColumn();
    }
};
