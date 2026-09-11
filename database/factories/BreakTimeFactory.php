<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakTime>
 */
class BreakTimeFactory extends Factory
{
    protected $model = BreakTime::class;

    /**
     * Define the model's default state
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ];
    }
}
