<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'version', 'is_active'
    ];

    public function fields()
    {
        return $this->hasMany(DocumentField::class)->orderBy('order');
    }

    public function templates()
    {
        return $this->hasMany(DocumentTemplate::class);
    }

    public function activeTemplate()
    {
        return $this->hasOne(DocumentTemplate::class)->where('is_active', true);
    }
}
