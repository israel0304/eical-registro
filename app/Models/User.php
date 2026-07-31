<?php

namespace App\Models;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'password_set_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(string $name): bool
    {
        return $this->roles->contains(fn ($role) => $role->name === $name);
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
        return $this->hasRole('Ponente');
    }

    public function isAsistente(): bool
    {
        return $this->hasRole('Asistente');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('Administrator');
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('Instructor');
    }

    public function hasSetPassword(): bool
    {
        return $this->password_set_at !== null;
    }
}
