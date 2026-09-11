<?php

namespace Database\Factories;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequestBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceCorrectionRequestBreakFactory extends Factory
{
    protected $model = AttendanceCorrectionRequestBreak::class;

    public function definition(): array
    {
        return [
            'attendance_correction_request_id' => AttendanceCorrectionRequest::factory(),

            'requested_break_in' => '12:00',
            'requested_break_out' => '13:00',
        ];
    }
}
