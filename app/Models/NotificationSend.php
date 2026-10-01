<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationSend extends Model
{
    /**
     * Campaña combinada: la audiencia es una lista de segmentos dentro de
     * audience_value['segments']. Los demás tipos se conservan para leer el
     * historial de campañas creadas antes de esta modalidad.
     */
    public const AUDIENCE_SEGMENTS = 'segments';

    /**
     * El orden importa: los índices 0..4 son usados por código existente para
     * resolver audiencias de un solo tipo, por lo que 'segments' se agrega al
     * final.
     */
    public const AUDIENCE_TYPES = ['all_users', 'role', 'speakers_by_kind', 'individual', 'workshop_enrollment', self::AUDIENCE_SEGMENTS];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'subject',
        'body_html',
        'audience_type',
        'audience_value',
        'template_id',
        'sent_by',
        'recipient_count',
        'sent_count',
        'failed_count',
        'status',
        'error',
        'completed_at',
    ];

    protected $casts = [
        'audience_value' => 'array',
        'recipient_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
