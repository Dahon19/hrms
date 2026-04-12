<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'document',
        'gender',
        'document_category_id',
        'document_subcategory_id',
    ];

    public function employees(){
        return $this->belongsToMany(Employee::class, 'employee_documents');
    }

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(DocumentSubcategory::class, 'document_subcategory_id');
    }
}
