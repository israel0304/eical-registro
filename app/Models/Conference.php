<?php

namespace App\Models;

use App\Models\Concerns\EmiteEventos;
use App\Models\Concerns\EnlazaPrograma;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conference extends Model
{
    use EmiteEventos, EnlazaPrograma, HasFactory, SoftDeletes;

    protected static string $programActivityType = 'conference';

    public const KINDS = ['magistral', 'especial', 'simposio', 'mesa_dialogo'];

    public const ROLES = ['speaker', 'moderator'];

    protected $fillable = [
        'title',
        'kind',
        'description',
        'location',
        'day',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected $casts = [
        'day' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conference_members')
            ->withPivot('role', 'activated', 'activated_at')
            ->withTimestamps();
    }

    public function speakers(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'speaker');
    }

    public function moderators(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'moderator');
    }
}
