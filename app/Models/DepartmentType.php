<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentType extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function departments()
    {
        return $this->hasMany(Department::class, 'department_type', 'name');
    }
}
