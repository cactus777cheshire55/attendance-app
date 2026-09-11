<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class, 'attendance_record_id');
    }

    public function attendanceCorrectionRequest(): HasOne
    {
        return $this->hasOne(AttendanceCorrectionRequest::class)->latestOfMany();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    public function getFormattedDateAttribute(): string
    {
        if (! $this->date) {
            return '';
        }

        $date = Carbon::parse($this->date);
        $weeks = ['日', '月', '火', '水', '木', '金', '土'];

        return $date->format('m/d').'('.$weeks[$date->dayOfWeek].')';
    }

    public function getFormattedClockInAttribute(): string
    {
        return $this->clock_in ? Carbon::parse($this->clock_in)->format('H:i') : '';
    }

    public function getFormattedClockOutAttribute(): string
    {
        return $this->clock_out ? Carbon::parse($this->clock_out)->format('H:i') : '';
    }

    public function getTotalBreakMinutesAttribute(): int
    {
        return $this->breakTimes->sum(function ($break) {
            if ($break->break_in && $break->break_out) {
                return Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
            }

            return 0;
        });
    }

    public function getFormattedTotalBreakTimeAttribute(): string
    {
        $minutes = $this->total_break_minutes;
        if ($minutes === 0) {
            return '00:00';
        }
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    public function getTotalWorkMinutesAttribute(): int
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return 0;
        }

        $clockIn = Carbon::parse($this->clock_in);
        $clockOut = Carbon::parse($this->clock_out);

        $totalPresenceMinutes = $clockIn->diffInMinutes($clockOut);

        return max(0, $totalPresenceMinutes - $this->total_break_minutes);
    }

    public function getFormattedTotalTimeAttribute(): string
    {
        $minutes = $this->total_work_minutes;
        if ($minutes === 0) {
            return '';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }
}
