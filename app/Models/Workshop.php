<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workshop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'capacity',
        'location',
        'day',
        'start_time',
        'end_time',
        'created_by',
        'qr_time_restricted',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workshop_instructor_user')
            ->withPivot('institution')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(WorkshopEnrollment::class);
    }

    public function enrolledUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workshop_enrollments')
            ->withPivot('enrolled_at', 'status')
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function enrolledCount(): int
    {
        return $this->enrollments()->where('status', 'enrolled')->count();
    }

    public function hasAvailableSpots(): bool
    {
        return $this->enrolledCount() < $this->capacity;
    }

    public function availableSpots(): int
    {
        return $this->capacity - $this->enrolledCount();
    }
}
