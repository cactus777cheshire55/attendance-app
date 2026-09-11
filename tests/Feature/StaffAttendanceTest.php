<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'admin_status' => false,
        ]);
    }

    private function attendance(User $user, string $date): AttendanceRecord
    {
        return AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $date.' 09:00:00',
            'clock_out' => $date.' 18:00:00',
            'comment' => '通常勤務',
        ]);
    }

    public function test_staff_list_displays_only_own_attendance_records(): void
    {
        $user = $this->user();
        $other = $this->user();

        $own = $this->attendance($user, '2026-09-01');
        $otherRecord = $this->attendance($other, '2026-09-01');

        $response = $this
            ->actingAs($user)
            ->get('/attendance/list?date=2026-09-01');

        $response->assertOk();

        $response->assertSee($own->formatted_clock_in);
        $response->assertDontSee($otherRecord->formatted_clock_in);
    }

    public function test_staff_list_defaults_to_current_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 7));

        $user = $this->user();

        $response = $this
            ->actingAs($user)
            ->get('/attendance/list');

        $response->assertOk();

        $response->assertViewHas('date', function ($date) {
            return $date->format('Y-m') === '2026-09';
        });

        Carbon::setTestNow();
    }

    public function test_previous_month_is_displayed(): void
    {
        $user = $this->user();

        $response = $this
            ->actingAs($user)
            ->get('/attendance/list?date=2026-09-15');

        $response->assertOk();

        $response->assertViewHas(
            'previousMonth',
            '2026-08'
        );
    }

    public function test_next_month_is_displayed(): void
    {
        $user = $this->user();

        $response = $this
            ->actingAs($user)
            ->get('/attendance/list?date=2026-09-15');

        $response->assertOk();

        $response->assertViewHas(
            'nextMonth',
            '2026-10'
        );
    }

    public function test_staff_can_open_attendance_detail(): void
    {
        $user = $this->user();

        $attendance = $this->attendance(
            $user,
            '2026-09-01'
        );

        $response = $this
            ->actingAs($user)
            ->get('/attendance/'.$attendance->id);

        $response->assertOk();

        $response->assertViewHas('data', function ($data) use ($attendance) {
            return $data['id'] === $attendance->id
                && $data['year'] === '2026年'
                && $data['date'] === '9月1日'
                && $data['clock_in'] === '09:00'
                && $data['clock_out'] === '18:00';
        });
    }

    public function test_staff_cannot_open_other_users_attendance_detail(): void
    {
        $user = $this->user();
        $other = $this->user();

        $attendance = $this->attendance(
            $other,
            '2026-09-01'
        );

        $response = $this
            ->actingAs($user)
            ->get('/attendance/'.$attendance->id);

        $response->assertNotFound();
    }

    public function test_attendance_detail_displays_break_times(): void
    {
        $user = $this->user();

        $attendance = $this->attendance(
            $user,
            '2026-09-01'
        );

        BreakTime::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '2026-09-01 12:00:00',
            'break_out' => '2026-09-01 13:00:00',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/attendance/'.$attendance->id);

        $response->assertOk();

        $response->assertViewHas('data', function ($data) {
            return $data['breaks'][0]['break_in'] === '12:00'
                && $data['breaks'][0]['break_out'] === '13:00';
        });
    }
}
