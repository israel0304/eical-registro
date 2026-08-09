<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_key',
        'name',
        'subject',
        'body_html',
    ];

    public function triggers(): HasMany
    {
        return $this->hasMany(EmailTrigger::class);
    }
}
