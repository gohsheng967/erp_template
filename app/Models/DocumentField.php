<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentField extends Model
{
    protected $fillable = [
        'document_type_id',
        'label',
        'field_name',
        'field_type',
        'is_required',
        'options',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }
}
