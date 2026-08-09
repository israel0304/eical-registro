<?php

namespace App\Models;

use App\Models\Concerns\EmiteEventos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopEnrollment extends Model
{
    use EmiteEventos, HasFactory;

    protected $fillable = [
        'user_id',
        'workshop_id',
        'enrolled_at',
        'status',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
