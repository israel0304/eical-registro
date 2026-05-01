<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id', 'event_day', 'registered_by',
        'certificate_generated', 'certificate_generated_at',
    ];

    protected $casts = [
        'certificate_generated' => 'boolean',
        'certificate_generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
