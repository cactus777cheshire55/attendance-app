<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequestBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_request_id',
        'requested_break_in',
        'requested_break_out',
    ];

    public function request()
    {
        return $this->belongsTo(
            AttendanceCorrectionRequest::class,
            'attendance_correction_request_id'
        );
    }
}
