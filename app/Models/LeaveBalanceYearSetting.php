<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalanceYearSetting extends Model
{
    protected $fillable = [
        'year',
        'starting_balance',
        'eligibility_months',
    ];
}
