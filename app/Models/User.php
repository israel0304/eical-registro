<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'dni',
        'affiliation',
        'country',
        'state',

        'profile_photo_path',
        'role_id',
        'is_active',
        'activation_token',
        'password_set_at',
        'semblanza',
    ];

    protected $appends = [
        'name',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'activation_token',
    ];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'password_set_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function presentations(): BelongsToMany
    {
        return $this->belongsToMany(Presentation::class, 'presentation_authors')
            ->withPivot('author_order', 'presented', 'presented_at')
            ->withTimestamps();
    }

    public function enrolledWorkshops(): BelongsToMany
    {
        return $this->belongsToMany(Workshop::class, 'workshop_enrollments')
            ->withPivot('enrolled_at', 'status')
            ->withTimestamps();
    }

    public function createdWorkshops(): HasMany
    {
        return $this->hasMany(Workshop::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->first_name.' '.$this->last_name),
        );
    }

    public function isPonente(): bool
    {
        return $this->role?->name === 'Ponente';
    }

    public function isAsistente(): bool
    {
        return $this->role?->name === 'Asistente';
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'Administrator';
    }

    public function hasSetPassword(): bool
    {
        return $this->password_set_at !== null;
    }
}
