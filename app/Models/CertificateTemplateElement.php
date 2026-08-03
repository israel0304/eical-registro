<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateTemplateElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'type',
        'content',
        'variable',
        'x',
        'y',
        'width',
        'height',
        'font_size',
        'font_weight',
        'font_family',
        'color',
        'text_align',
        'z_index',
    ];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'width' => 'integer',
        'height' => 'integer',
        'font_size' => 'integer',
        'z_index' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }
}
