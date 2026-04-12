<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePosition extends Model
{
    protected $fillable = [
        'employee_id',
        'position_id',
    ];
    
    public function employee(){
        return $this->belongsTo(Employee::class);
    }

    public function position(){
        return $this->belongsTo(Position::class);
    }
}
