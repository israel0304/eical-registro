<?php

namespace App\Models;

use App\Models\Concerns\EmiteEventos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Presentation extends Model
{
    use EmiteEventos, HasFactory, SoftDeletes;

    protected $fillable = [
        'submission_id',
        'title',
        'abstract',
        'discipline',
        'keywords',
        'location',
        'day',
        'start_time',
        'end_time',
    ];

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'presentation_authors')
            ->withPivot('author_order', 'presented', 'presented_at')
            ->withTimestamps();
    }

    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'presentation_moderators')
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function primaryAuthor()
    {
        return $this->authors()
            ->wherePivot('author_order', 1)
            ->first();
    }
}
