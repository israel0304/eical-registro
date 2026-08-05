<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'kind',
        'participation_type_id',
        'is_default',
        'background_path',
        'width',
        'height',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function participationType(): BelongsTo
    {
        return $this->belongsTo(ParticipationType::class);
    }

    public function scopeKind($query, string $kind)
    {
        return $query->where('kind', $kind);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(CertificateTemplateElement::class, 'template_id')->orderBy('z_index');
    }
}
