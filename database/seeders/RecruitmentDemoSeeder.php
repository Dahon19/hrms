<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RecruitmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ApplicantSeeder::class,
        ]);
    }
}
