<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REDACTED_VALUE = '[protected]';

    private const PDS_AUDITABLE_TYPES = [
        'App\\Models\\PdsProfile',
        'App\\Models\\PdsPersonalInfo',
        'App\\Models\\PdsFamilyBackground',
        'App\\Models\\PdsChild',
        'App\\Models\\PdsEducation',
        'App\\Models\\PdsCivilServiceEligibility',
        'App\\Models\\PdsWorkExperience',
        'App\\Models\\PdsVoluntaryWork',
        'App\\Models\\PdsTraining',
        'App\\Models\\PdsOtherInfo',
    ];

    private const ENCRYPTED_COLUMNS = [
        'pds_profiles' => ['hr_remarks', 'notes'],
        'pds_personal_infos' => [
            'last_name',
            'first_name',
            'middle_name',
            'name_extension',
            'birth_date',
            'birth_place',
            'sex',
            'civil_status',
            'citizenship',
            'height_m',
            'weight_kg',
            'blood_type',
            'gsis_no',
            'sss_no',
            'tin_no',
            'philhealth_no',
            'residential_address',
            'permanent_address',
            'telephone_no',
            'mobile_no',
            'email_address',
        ],
        'pds_family_backgrounds' => [
            'spouse_last_name',
            'spouse_first_name',
            'spouse_middle_name',
            'spouse_occupation',
            'spouse_employer',
            'spouse_business_address',
            'spouse_telephone',
            'father_last_name',
            'father_first_name',
            'father_middle_name',
            'father_name_extension',
            'mother_last_name',
            'mother_first_name',
            'mother_middle_name',
        ],
        'pds_children' => ['full_name', 'birth_date'],
        'pds_educations' => [
            'school_name',
            'degree_course',
            'date_from',
            'date_to',
            'highest_level_units',
            'year_graduated',
            'honors_received',
        ],
        'pds_civil_service_eligibilities' => [
            'eligibility_type',
            'rating',
            'exam_date',
            'exam_place',
            'license_number',
            'validity_date',
        ],
        'pds_work_experiences' => [
            'date_from',
            'date_to',
            'position_title',
            'department_office',
            'salary_grade',
            'appointment_status',
        ],
        'pds_voluntary_works' => [
            'organization_name',
            'date_from',
            'date_to',
            'hours',
            'position_nature',
        ],
        'pds_trainings' => [
            'title',
            'date_from',
            'date_to',
            'hours',
            'training_type',
            'conducted_by',
        ],
        'pds_other_infos' => ['description'],
    ];

    private const MYSQL_INDEXES_TO_DROP = [
        'pds_personal_infos' => ['pds_personal_infos_birth_date_index'],
        'pds_children' => ['pds_children_pds_profile_id_birth_date_index'],
        'pds_educations' => ['pds_educations_date_from_date_to_index'],
        'pds_civil_service_eligibilities' => ['pds_civil_service_eligibilities_pds_profile_id_exam_date_index'],
        'pds_work_experiences' => ['pds_work_experiences_pds_profile_id_date_from_date_to_index'],
        'pds_voluntary_works' => ['pds_voluntary_works_pds_profile_id_date_from_date_to_index'],
        'pds_trainings' => ['pds_trainings_pds_profile_id_date_from_date_to_index'],
    ];

    private const MYSQL_FK_INDEXES_TO_ADD = [
        'pds_children' => ['pds_profile_id'],
        'pds_civil_service_eligibilities' => ['pds_profile_id'],
        'pds_work_experiences' => ['pds_profile_id'],
        'pds_voluntary_works' => ['pds_profile_id'],
        'pds_trainings' => ['pds_profile_id'],
    ];

    private const MYSQL_COLUMNS_TO_TEXT = [
        'pds_personal_infos' => [
            'last_name',
            'first_name',
            'middle_name',
            'name_extension',
            'birth_date',
            'birth_place',
            'sex',
            'civil_status',
            'citizenship',
            'height_m',
            'weight_kg',
            'blood_type',
            'gsis_no',
            'sss_no',
            'tin_no',
            'philhealth_no',
            'telephone_no',
            'mobile_no',
            'email_address',
        ],
        'pds_family_backgrounds' => [
            'spouse_last_name',
            'spouse_first_name',
            'spouse_middle_name',
            'spouse_occupation',
            'spouse_employer',
            'spouse_business_address',
            'spouse_telephone',
            'father_last_name',
            'father_first_name',
            'father_middle_name',
            'father_name_extension',
            'mother_last_name',
            'mother_first_name',
            'mother_middle_name',
        ],
        'pds_children' => ['full_name', 'birth_date'],
        'pds_educations' => [
            'school_name',
            'degree_course',
            'date_from',
            'date_to',
            'highest_level_units',
            'year_graduated',
            'honors_received',
        ],
        'pds_civil_service_eligibilities' => [
            'eligibility_type',
            'rating',
            'exam_date',
            'exam_place',
            'license_number',
            'validity_date',
        ],
        'pds_work_experiences' => [
            'date_from',
            'date_to',
            'position_title',
            'department_office',
            'salary_grade',
            'appointment_status',
        ],
        'pds_voluntary_works' => [
            'organization_name',
            'date_from',
            'date_to',
            'hours',
            'position_nature',
        ],
        'pds_trainings' => [
            'title',
            'date_from',
            'date_to',
            'hours',
            'training_type',
            'conducted_by',
        ],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->ensureMySqlForeignKeyIndexes();
            $this->dropMySqlIndexes();
            $this->convertMySqlColumnsToText();
        }

        $this->encryptExistingPdsData();
        $this->redactExistingPdsAuditMetadata();
    }

    public function down(): void
    {
        $this->decryptExistingPdsData();
    }

    private function dropMySqlIndexes(): void
    {
        foreach (self::MYSQL_INDEXES_TO_DROP as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $index) {
                if ($this->mysqlIndexExists($table, $index)) {
                    DB::statement(sprintf('DROP INDEX `%s` ON `%s`', $index, $table));
                }
            }
        }
    }

    private function ensureMySqlForeignKeyIndexes(): void
    {
        foreach (self::MYSQL_FK_INDEXES_TO_ADD as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                $index = sprintf('%s_%s_index', $table, $column);
                if ($this->mysqlIndexExists($table, $index)) {
                    continue;
                }

                DB::statement(sprintf('CREATE INDEX `%s` ON `%s` (`%s`)', $index, $table, $column));
            }
        }
    }

    private function convertMySqlColumnsToText(): void
    {
        foreach (self::MYSQL_COLUMNS_TO_TEXT as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::statement(sprintf('ALTER TABLE `%s` MODIFY `%s` TEXT NULL', $table, $column));
            }
        }
    }

    private function encryptExistingPdsData(): void
    {
        foreach (self::ENCRYPTED_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table, $columns): void {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($columns as $column) {
                            if (!property_exists($row, $column)) {
                                continue;
                            }

                            $value = $row->{$column};
                            if ($value === null) {
                                continue;
                            }

                            $stringValue = (string) $value;
                            if ($this->isAlreadyEncrypted($stringValue)) {
                                continue;
                            }

                            $updates[$column] = Crypt::encryptString($stringValue);
                        }

                        if ($updates !== []) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    private function decryptExistingPdsData(): void
    {
        foreach (self::ENCRYPTED_COLUMNS as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table, $columns): void {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($columns as $column) {
                            if (!property_exists($row, $column)) {
                                continue;
                            }

                            $value = $row->{$column};
                            if (!is_string($value) || !$this->isAlreadyEncrypted($value)) {
                                continue;
                            }

                            $updates[$column] = Crypt::decryptString($value);
                        }

                        if ($updates !== []) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    private function redactExistingPdsAuditMetadata(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')
            ->whereIn('auditable_type', self::PDS_AUDITABLE_TYPES)
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $metadata = $this->decodeMetadata($row->metadata);
                    if ($metadata === []) {
                        continue;
                    }

                    $updated = false;

                    foreach (['attributes', 'changes'] as $key) {
                        if (!isset($metadata[$key]) || !is_array($metadata[$key])) {
                            continue;
                        }

                        $metadata[$key] = collect($metadata[$key])
                            ->map(fn () => self::REDACTED_VALUE)
                            ->all();
                        $updated = true;
                    }

                    if ($updated) {
                        DB::table('audit_logs')
                            ->where('id', $row->id)
                            ->update(['metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    }
                }
            });
    }

    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (!is_string($metadata) || $metadata === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    private function mysqlIndexExists(string $table, string $index): bool
    {
        return !empty(DB::select(sprintf('SHOW INDEX FROM `%s` WHERE Key_name = ?', $table), [$index]));
    }
};
