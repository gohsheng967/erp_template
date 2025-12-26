<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'document_type_id',
        'name',
        'version',
        'html_template',
        'css',
        'is_active',
    ];

    public function type()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
