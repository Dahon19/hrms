<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasTable('users')) {
            return;
        }

        if (!$this->indexExists('employees', 'idx_employees_last_first')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index(['last_name', 'first_name'], 'idx_employees_last_first');
            });
        }

        if (!$this->indexExists('employees', 'idx_employees_first_middle_last')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index(['first_name', 'middle_name', 'last_name'], 'idx_employees_first_middle_last');
            });
        }

        if (!$this->indexExists('users', 'idx_users_archived_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['archived_at'], 'idx_users_archived_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && $this->indexExists('employees', 'idx_employees_last_first')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('idx_employees_last_first');
            });
        }

        if (Schema::hasTable('employees') && $this->indexExists('employees', 'idx_employees_first_middle_last')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('idx_employees_first_middle_last');
            });
        }

        if (Schema::hasTable('users') && $this->indexExists('users', 'idx_users_archived_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('idx_users_archived_at');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                $name = (string) ($row->name ?? '');
                if ($name === $index) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();
        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
