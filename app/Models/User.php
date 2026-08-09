<?php

namespace App\Models;

use App\Models\Concerns\EmiteEventos;
use App\Services\EventAudit;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use EmiteEventos, HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

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
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        EventAudit::emit('user.registered', $this, null, [
            'destinatario' => $this->email,
            'nombre_completo' => $this->name,
            'nombre' => $this->first_name,
            'url_verificacion' => $verificationUrl,
        ]);
    }

    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ], false));

        EventAudit::emit('user.password_reset', $this, null, [
            'destinatario' => $this->email,
            'nombre_completo' => $this->name,
            'nombre' => $this->first_name,
            'url_restablecer' => $resetUrl,
        ]);
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
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
        'checkin_token',
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

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->checkin_token)) {
                $user->checkin_token = 'GFT-'.strtoupper(Str::random(16));
            }
        });
    }

    public function ensureCheckinToken(): string
    {
        if (! empty($this->checkin_token)) {
            return $this->checkin_token;
        }

        $this->update(['checkin_token' => 'GFT-'.strtoupper(Str::random(16))]);

        return $this->checkin_token;
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

    public function moderatedWorkshops(): BelongsToMany
    {
        return $this->belongsToMany(Workshop::class, 'workshop_moderator_user')->withTimestamps();
    }

    public function moderatedPresentations(): BelongsToMany
    {
        return $this->belongsToMany(Presentation::class, 'presentation_moderators')->withTimestamps();
    }

    public function moderatedConferences(): BelongsToMany
    {
        return $this->belongsToMany(Conference::class, 'conference_members')
            ->wherePivot('role', 'moderator')
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

    public function createdConferences(): HasMany
    {
        return $this->hasMany(Conference::class, 'created_by');
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
        return $this->hasRole(config('roles.super_admin'));
    }

    public function isInstructor(): bool
    {
        return $this->hasRole('Instructor');
    }

    public function isSpeaker(): bool
    {
        return $this->hasRole('Speaker');
    }

    public function isModerator(): bool
    {
        return $this->hasRole('Moderator');
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissionKeys(), true);
    }

    public function canViewActivity(string $moduleView, bool $isAssigned): bool
    {
        return $this->can($moduleView) || $isAssigned;
    }

    public function canScoped(string $permission, string $moduleView, bool $isAssigned): bool
    {
        return $this->can($permission) && ($this->can($moduleView) || $isAssigned);
    }

    public function permissionKeys(): array
    {
        return Permission::query()
            ->whereHas('roles', fn ($q) => $q
                ->whereIn('roles.id', $this->roles()->pluck('roles.id'))
                ->where('is_active', true))
            ->pluck('key')
            ->all();
    }

    public function hasSetPassword(): bool
    {
        return $this->password_set_at !== null;
    }
}
