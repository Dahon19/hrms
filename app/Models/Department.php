<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'department',
        'department_type',
        'logo_path',
    ];
    
    public function employees(){
        return $this->hasMany(Employee::class);
    }

    public function type()
    {
        return $this->belongsTo(DepartmentType::class, 'department_type', 'name');
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
