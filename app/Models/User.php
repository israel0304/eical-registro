<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'dni',
        'phone',
        'extension',
        'tshirt_size',
        'profile_photo_path',
        'role_id',
        'stand_id',
        'is_active',
        'unit_id',
        'department_id',
        'custom_unit',
        'custom_department',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function stand()
    {
        return $this->belongsTo(Stand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Virtual attribute for full name compatibility (e.g. for AppSidebar profile)
     */
    protected function name(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn () => trim($this->first_name.' '.$this->last_name),
        );
    }
}
