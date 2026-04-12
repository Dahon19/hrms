<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        /**
         * max_days meanings:
         *  - NULL  → accrual-based (Vacation Leave / Sick Leave formula: earned days per year)
         *  - int   → fixed statutory allocation per year for that leave type
         *
         * Philippine statutory references:
         *  VL / SL  : 5 days/year + 1 per month worked = up to 17 (RA 6713 / CSC)
         *  Maternity: 105 days (RA 11210), extendable by 30 unpaid
         *  Paternity: 7 days (RA 8187)
         *  Solo Parent: 7 days/year (RA 8972)
         *  Bereavement: 3 days (company-standard; no specific statutory mandate)
         *  VAWC: 10 days (RA 9262)
         *  Magna Carta: 60 days/year (RA 9710)
         *  Emergency: 3 days (policy-based)
         *  Birthday: 1 day (policy-based)
         *  Study Leave: 6 months total; use 180 as ceiling (CSC MC No. 10)
         *  CTO (Service Credit): accrued from overtime; treated as NULL (accrual)
         */
        $leaveTypes = [
            [
                'id'                  => 1,
                'name'                => 'Vacation Leave',
                'color_code'          => '#3498db',
                'requires_attachment' => 0,
                'max_days'            => null, // accrual-based
                'gender'              => null,
            ],
            [
                'id'                  => 2,
                'name'                => 'Sick Leave',
                'color_code'          => '#e74c3c',
                'requires_attachment' => 1,
                'max_days'            => null, // accrual-based
                'gender'              => null,
            ],
            [
                'id'                  => 3,
                'name'                => 'Maternity Leave',
                'color_code'          => '#1abc9c',
                'requires_attachment' => 1,
                'max_days'            => 105,
                'gender'              => 'female',
            ],
            [
                'id'                  => 4,
                'name'                => 'Paternity Leave',
                'color_code'          => '#16a085',
                'requires_attachment' => 1,
                'max_days'            => 7,
                'gender'              => 'male',
            ],
            [
                'id'                  => 5,
                'name'                => 'Solo Parent Leave',
                'color_code'          => '#8e44ad',
                'requires_attachment' => 1,
                'max_days'            => 7,
                'gender'              => null,
            ],
            [
                'id'                  => 6,
                'name'                => 'Bereavement Leave',
                'color_code'          => '#2c3e50',
                'requires_attachment' => 1,
                'max_days'            => 3,
                'gender'              => null,
            ],
            [
                'id'                  => 7,
                'name'                => 'VAWC Leave',
                'color_code'          => '#9b59b6',
                'requires_attachment' => 1,
                'max_days'            => 10,
                'gender'              => 'female',
            ],
            [
                'id'                  => 8,
                'name'                => 'Magna Carta Leave',
                'color_code'          => '#6c5ce7',
                'requires_attachment' => 1,
                'max_days'            => 60,
                'gender'              => 'female',
            ],
            [
                'id'                  => 9,
                'name'                => 'Emergency Leave',
                'color_code'          => '#f1c40f',
                'requires_attachment' => 0,
                'max_days'            => 3,
                'gender'              => null,
            ],
            [
                'id'                  => 10,
                'name'                => 'Birthday Leave',
                'color_code'          => '#e67e22',
                'requires_attachment' => 0,
                'max_days'            => 1,
                'gender'              => null,
            ],
            [
                'id'                  => 11,
                'name'                => 'Study Leave',
                'color_code'          => '#2980b9',
                'requires_attachment' => 1,
                'max_days'            => 180,
                'gender'              => null,
            ],
            [
                'id'                  => 12,
                'name'                => 'Compensatory Time Off (CTO)',
                'color_code'          => '#27ae60',
                'requires_attachment' => 0,
                'max_days'            => null, // accrual-based (earned from service credit)
                'gender'              => null,
            ],
        ];

        foreach ($leaveTypes as $leave) {
            DB::table('leave_types')->updateOrInsert(
                ['id' => $leave['id']],
                array_merge($leave, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
