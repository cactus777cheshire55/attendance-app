<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'user_id',
        'requested_date',
        'requested_clock_in',
        'requested_clock_out',
        'reason',
        'status',
    ];

    protected $appends = [
        'approval_status',
    ];

    public function breaks()
    {
        return $this->hasMany(
            AttendanceCorrectionRequestBreak::class,
            'attendance_correction_request_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function getApprovalStatusAttribute(): string
    {
        return match ((string) $this->status) {
            'approved', '1' => '承認済み',
            'pending', '0' => '承認待ち',
            default => '承認待ち',
        };
    }

    public function getNewDateAttribute()
    {
        $rawDate = $this->requested_date
            ?? $this->attendanceRecord?->date;

        return $rawDate ? Carbon::parse($rawDate) : null;
    }

    public function getClockInAttribute()
    {
        $clockIn = $this->attributes['clock_in']
            ?? $this->requested_clock_in
            ?? $this->attendanceRecord?->clock_in;

        return $clockIn ? Carbon::parse($clockIn)->format('H:i') : '';
    }

    public function getClockOutAttribute()
    {
        $clockOut = $this->attributes['clock_out']
            ?? $this->requested_clock_out
            ?? $this->attendanceRecord?->clock_out;

        return $clockOut ? Carbon::parse($clockOut)->format('H:i') : '';
    }

    public function getProposalBreaksAttribute()
    {
        return $this->breaks->isNotEmpty()
            ? $this->breaks
            : ($this->attendanceRecord?->breakTimes ?? collect([]));
    }

    public function getCommentAttribute()
    {
        return $this->attributes['comment']
            ?? $this->reason
            ?? '';
    }
}
