<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProgramItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'location',
        'day',
        'start_time',
        'end_time',
        'block_type',
        'activity_type',
        'activity_id',
        'created_by',
    ];

    protected $casts = [
        'day' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function activity(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTimeLabelAttribute(): string
    {
        if ($this->start_time === null && $this->end_time === null) {
            return '';
        }

        $format = fn ($t) => $t?->format('H:i');

        if ($this->start_time !== null && $this->end_time !== null) {
            return "{$format($this->start_time)} - {$format($this->end_time)}";
        }

        return $format($this->start_time ?? $this->end_time) ?? '';
    }
}
