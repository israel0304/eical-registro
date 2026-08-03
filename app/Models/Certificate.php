<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio',
        'user_id',
        'participation_type_id',
        'template_id',
        'event_id',
        'event_type',
        'metadata',
        'downloaded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'downloaded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participationType(): BelongsTo
    {
        return $this->belongsTo(ParticipationType::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    public function event(): MorphTo
    {
        return $this->morphTo();
    }
}
