<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSubcategory extends Model
{
    protected $fillable = [
        'document_category_id',
        'name',
    ];

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
