<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EventLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'trigger_id',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'payload',
        'status',
        'message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(EmailTrigger::class);
    }
}
