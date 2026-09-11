<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'admin_status' => true,
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create([
            'admin_status' => false,
        ]);
    }

    private function attendance(User $user, string $date = '2026-09-01'): AttendanceRecord
    {
        return AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $date.' 09:00:00',
            'clock_out' => $date.' 18:00:00',
            'comment' => '通常勤務',
        ]);
    }

    public function test_non_admin_cannot_access_admin_attendance_list(): void
    {
        $user = $this->staff();

        $response = $this
            ->actingAs($user)
            ->get('/admin/attendance/list');

        $response->assertRedirect();
    }

    public function test_admin_can_view_all_users_attendance_for_selected_date(): void
    {
        $admin = $this->admin();

        $user1 = $this->staff();
        $user2 = $this->staff();

        $attendance1 = $this->attendance(
            $user1,
            '2026-09-01'
        );

        $attendance2 = $this->attendance(
            $user2,
            '2026-09-01'
        );

        $response = $this
            ->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-09-01');

        $response->assertOk();

        $response->assertViewHas('attendanceRecords', function ($records) use (
            $attendance1,
            $attendance2
        ) {
            return $records->contains('id', $attendance1->id)
                && $records->contains('id', $attendance2->id);
        });
    }

    public function test_admin_attendance_list_defaults_to_today(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 9, 7, 10, 0, 0)
        );

        $admin = $this->admin();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/attendance/list');

        $response->assertOk();

        $response->assertViewHas('date', function ($date) {
            return $date->toDateString() === '2026-09-07';
        });

        Carbon::setTestNow();
    }

    public function test_admin_previous_day_is_correct(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-09-07');

        $response->assertOk();

        $response->assertViewHas(
            'previousDay',
            '2026-09-06'
        );
    }

    public function test_admin_next_day_is_correct(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-09-07');

        $response->assertOk();

        $response->assertViewHas(
            'nextDay',
            '2026-09-08'
        );
    }

    public function test_admin_can_view_staff_list(): void
    {
        $admin = $this->admin();

        $user1 = $this->staff();
        $user2 = $this->staff();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/staff/list');

        $response->assertOk();

        $response->assertViewHas('users', function ($users) use (
            $user1,
            $user2
        ) {
            return $users->contains('id', $user1->id)
                && $users->contains('id', $user2->id);
        });
    }

    public function test_admin_can_view_staff_attendance(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $attendance = $this->attendance(
            $staff,
            '2026-09-01'
        );

        $response = $this
            ->actingAs($admin)
            ->get('/admin/attendance/staff/'.$staff->id.'?date=2026-09-01');

        $response->assertOk();

        $response->assertViewHas('user', $staff);

        $response->assertViewHas(
            'formattedAttendanceRecords',
            function ($records) use ($attendance) {
                return $records->contains(
                    fn ($record) => $record['id'] === $attendance->id
                );
            }
        );
    }

    public function test_admin_can_view_attendance_detail(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $attendance = $this->attendance(
            $staff,
            '2026-09-01'
        );

        $response = $this
            ->actingAs($admin)
            ->get('/admin/attendance/'.$attendance->id);

        $response->assertOk();

        $response->assertViewHas(
            'attendanceRecord',
            function ($record) use ($attendance) {
                return $record['id'] === $attendance->id
                    && $record['clock_in'] === '09:00'
                    && $record['clock_out'] === '18:00';
            }
        );

        $response->assertViewHas(
            'user',
            $staff
        );
    }

    public function test_admin_can_update_attendance(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $attendance = $this->attendance(
            $staff,
            '2026-09-01'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, [
                'new_clock_in' => '10:00',
                'new_clock_out' => '19:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '管理者修正',
            ]);

        $response->assertRedirect(
            '/admin/attendance/'.$attendance->id
        );

        $attendance->refresh();

        $this->assertSame(
            '10:00',
            $attendance->clock_in->format('H:i')
        );

        $this->assertSame(
            '19:00',
            $attendance->clock_out->format('H:i')
        );

        $this->assertSame(
            '管理者修正',
            $attendance->comment
        );

        $this->assertDatabaseHas('break_times', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '2026-09-01 12:00:00',
            'break_out' => '2026-09-01 13:00:00',
        ]);
    }

    public function test_admin_update_rejects_invalid_clock_out(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $attendance = $this->attendance(
            $staff,
            '2026-09-01'
        );

        $response = $this
            ->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
                'comment' => '管理者修正',
            ]);

        $response->assertSessionHasErrors('new_clock_out');

        $this->assertStringContainsString(
            '出勤時間もしくは退勤時間が不適切な値です',
            session('errors')->first('new_clock_out')
        );
    }

    public function test_admin_application_list_can_be_opened(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $attendance = $this->attendance($staff);

        $application = AttendanceCorrectionRequest::factory()->create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $staff->id,
            'requested_date' => '2026-09-01',
            'requested_clock_in' => '2026-09-01 10:00:00',
            'requested_clock_out' => '2026-09-01 19:00:00',
            'reason' => '修正申請',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get('/admin/stamp_correction_request/list');

        $response->assertOk();

        $response->assertViewHas('applications', function ($applications) use ($application) {
            return $applications->contains(
                fn ($item) => $item['id'] === $application->id
            );
        });
    }

    public function test_admin_can_open_application_approval_detail(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $attendance = $this->attendance($staff);

        $application = AttendanceCorrectionRequest::factory()->create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $staff->id,
            'requested_date' => '2026-09-01',
            'requested_clock_in' => '2026-09-01 10:00:00',
            'requested_clock_out' => '2026-09-01 19:00:00',
            'reason' => '修正申請',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(
                '/stamp_correction_request/approve/'.$application->id
            );

        $response->assertOk();

        $response->assertViewHas(
            'application',
            $application
        );

        $response->assertViewHas(
            'user',
            $staff
        );
    }
}
