<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'admin_status' => false,
        ]);
    }

    public function test_attendance_page_displays_current_date_and_time(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 7, 10, 30, 0));

        $user = $this->user();

        $response = $this
            ->actingAs($user)
            ->get('/attendance');

        $response->assertOk();

        $response->assertSee('2026年9月7日');
        $response->assertSee('10:30');

        Carbon::setTestNow();
    }

    public function test_status_is_working_outside_when_no_attendance_exists(): void
    {
        $user = $this->user();

        $this->assertSame(
            '勤務外',
            $user->attendance_status
        );
    }

    public function test_status_is_working_when_clocked_in(): void
    {
        $user = $this->user();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHour(),
            'clock_out' => null,
        ]);

        $user->refresh();

        $this->assertSame(
            '出勤中',
            $user->attendance_status
        );
    }

    public function test_status_is_breaking_during_break(): void
    {
        $user = $this->user();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(2),
            'clock_out' => null,
        ]);

        BreakTime::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_in' => Carbon::now()->subMinutes(20),
            'break_out' => null,
        ]);

        $user->refresh();

        $this->assertSame(
            '休憩中',
            $user->attendance_status
        );
    }

    public function test_status_is_finished_after_clock_out(): void
    {
        $user = $this->user();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(8),
            'clock_out' => Carbon::now(),
        ]);

        $user->refresh();

        $this->assertSame(
            '退勤済',
            $user->attendance_status
        );
    }

    public function test_clock_in_creates_attendance_record(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 7, 9, 0, 0));

        $user = $this->user();

        $response = $this
            ->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_in',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-09-07',
        ]);

        $attendance = AttendanceRecord::where('user_id', $user->id)->first();

        $this->assertNotNull($attendance->clock_in);

        Carbon::setTestNow();
    }

    public function test_clock_in_can_only_be_done_once_per_day(): void
    {
        $user = $this->user();

        $first = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(8),
        ]);

        $this
            ->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_in',
            ]);

        $this->assertDatabaseCount('attendance_records', 1);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $first->id,
        ]);
    }

    public function test_break_in_creates_break_time(): void
    {
        $user = $this->user();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(3),
            'clock_out' => null,
        ]);

        $this
            ->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_in',
            ]);

        $this->assertDatabaseHas('break_times', [
            'attendance_record_id' => $attendance->id,
        ]);

        $this->assertSame(
            '休憩中',
            $user->fresh()->attendance_status
        );
    }

    public function test_break_out_updates_latest_open_break(): void
    {
        $user = $this->user();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(3),
        ]);

        $break = BreakTime::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_in' => Carbon::now()->subMinutes(30),
            'break_out' => null,
        ]);

        $this
            ->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_out',
            ]);

        $break->refresh();

        $this->assertNotNull($break->break_out);
        $this->assertSame(
            '出勤中',
            $user->fresh()->attendance_status
        );
    }

    public function test_multiple_breaks_are_allowed(): void
    {
        $user = $this->user();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(8),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this
                ->actingAs($user)
                ->post('/attendance', [
                    'action' => 'break_in',
                ]);

            $this
                ->actingAs($user)
                ->post('/attendance', [
                    'action' => 'break_out',
                ]);
        }

        $this->assertSame(
            3,
            $attendance->breakTimes()->count()
        );
    }

    public function test_clock_out_creates_clock_out_time(): void
    {
        $user = $this->user();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(8),
            'clock_out' => null,
        ]);

        $this
            ->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_out',
            ]);

        $attendance->refresh();

        $this->assertNotNull($attendance->clock_out);

        $this->assertSame(
            '退勤済',
            $user->fresh()->attendance_status
        );
    }

    public function test_clock_out_is_not_allowed_during_break(): void
    {
        $user = $this->user();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => Carbon::today()->toDateString(),
            'clock_in' => Carbon::now()->subHours(3),
            'clock_out' => null,
        ]);

        BreakTime::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_in' => Carbon::now()->subMinutes(20),
            'break_out' => null,
        ]);

        $this
            ->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_out',
            ]);

        $attendance->refresh();

        $this->assertNull($attendance->clock_out);
    }
}
