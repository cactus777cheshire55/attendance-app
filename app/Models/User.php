<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_status',
        'is_first_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
        'is_first_login' => 'boolean',
    ];

    public function attendance_records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function getAttendanceStatusAttribute(): string
    {
        if (session()->has('attendance_status')) {
            return session('attendance_status');
        }

        $attendance = $this->attendance_records()
            ->whereDate('date', Carbon::today())
            ->first();

        if (! $attendance) {
            return '勤務外';
        }

        if ($attendance->clock_out) {
            return '退勤済';
        }

        $isBreaking = $attendance->breakTimes()
            ->whereNull('break_out')
            ->exists();

        if ($isBreaking) {
            return '休憩中';
        }

        return '出勤中';
    }

    public function getAdminStatusAttribute(): bool
    {
        return (bool) ($this->attributes['admin_status'] ?? false);
    }
}
