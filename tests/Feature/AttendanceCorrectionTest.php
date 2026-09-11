<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequestBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'admin_status' => false,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'admin_status' => true,
        ]);
    }

    private function attendance(User $user): AttendanceRecord
    {
        return AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'clock_in' => '2026-09-01 09:00:00',
            'clock_out' => '2026-09-01 18:00:00',
            'comment' => '通常勤務',
        ]);
    }

    public function test_clock_in_after_clock_out_is_invalid(): void
    {
        $user = $this->user();
        $attendance = $this->attendance($user);

        $response = $this
            ->actingAs($user)
            ->post('/attendance/'.$attendance->id, [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
                'comment' => '修正理由',
            ]);

        $response->assertSessionHasErrors('new_clock_out');

        $this->assertStringContainsString(
            '出勤時間もしくは退勤時間が不適切な値です',
            session('errors')->first('new_clock_out')
        );
    }

    public function test_break_in_after_clock_out_is_invalid(): void
    {
        $user = $this->user();
        $attendance = $this->attendance($user);

        $response = $this
            ->actingAs($user)
            ->post('/attendance/'.$attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['19:00'],
                'new_break_out' => ['19:30'],
                'comment' => '修正理由',
            ]);

        $response->assertSessionHasErrors('new_break_in');

        $this->assertStringContainsString(
            '休憩時間もしくは退勤時間が不適切な値です',
            session('errors')->first('new_break_in')
        );
    }

    public function test_break_out_after_clock_out_is_invalid(): void
    {
        $user = $this->user();
        $attendance = $this->attendance($user);

        $response = $this
            ->actingAs($user)
            ->post('/attendance/'.$attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:00'],
                'new_break_out' => ['19:00'],
                'comment' => '修正理由',
            ]);

        $response->assertSessionHasErrors('new_break_out');

        $this->assertStringContainsString(
            '休憩時間もしくは退勤時間が不適切な値です',
            session('errors')->first('new_break_out')
        );
    }

    public function test_comment_is_required(): void
    {
        $user = $this->user();
        $attendance = $this->attendance($user);

        $response = $this
            ->actingAs($user)
            ->post('/attendance/'.$attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
            ]);

        $response->assertSessionHasErrors('comment');

        $this->assertStringContainsString(
            '備考を記入してください',
            session('errors')->first('comment')
        );
    }

    public function test_correction_request_is_created(): void
    {
        $user = $this->user();
        $attendance = $this->attendance($user);

        $response = $this
            ->actingAs($user)
            ->post('/attendance/'.$attendance->id, [
                'new_clock_in' => '09:30',
                'new_clock_out' => '18:30',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '出勤時間を修正します',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'attendance_record_id' => $attendance->id,
                'user_id' => $user->id,
                'requested_clock_in' => '2026-09-01 09:30:00',
                'requested_clock_out' => '2026-09-01 18:30:00',
                'reason' => '出勤時間を修正します',
                'status' => 'pending',
            ]
        );

        $request = AttendanceCorrectionRequest::latest('id')->first();

        $this->assertDatabaseHas(
            'attendance_correction_request_breaks',
            [
                'attendance_correction_request_id' => $request->id,
                'requested_break_in' => '12:00',
                'requested_break_out' => '13:00',
            ]
        );
    }

    public function test_user_application_list_displays_own_requests(): void
    {
        $user = $this->user();

        $attendance = $this->attendance($user);

        $request = AttendanceCorrectionRequest::factory()->create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_date' => '2026-09-01',
            'requested_clock_in' => '2026-09-01 09:30:00',
            'requested_clock_out' => '2026-09-01 18:30:00',
            'reason' => '修正申請',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/stamp_correction_request/list');

        $response->assertOk();

        $response->assertViewHas('formattedApplications', function ($applications) use ($request) {
            return $applications->contains(
                fn ($application) => $application['id'] === $request->id
                    && $application['approval_status'] === '承認待ち'
            );
        });
    }

    public function test_approved_request_is_displayed_as_approved(): void
    {
        $user = $this->user();

        $attendance = $this->attendance($user);

        $request = AttendanceCorrectionRequest::factory()->create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_date' => '2026-09-01',
            'requested_clock_in' => '2026-09-01 09:30:00',
            'requested_clock_out' => '2026-09-01 18:30:00',
            'reason' => '修正申請',
            'status' => 'approved',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/stamp_correction_request/list');

        $response->assertOk();

        $response->assertViewHas('formattedApplications', function ($applications) use ($request) {
            return $applications->contains(
                fn ($application) => $application['id'] === $request->id
                    && $application['approval_status'] === '承認済み'
            );
        });
    }

    public function test_application_detail_displays_requested_data(): void
    {
        $user = $this->user();

        $attendance = $this->attendance($user);

        $request = AttendanceCorrectionRequest::factory()->create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_date' => '2026-09-01',
            'requested_clock_in' => '2026-09-01 09:30:00',
            'requested_clock_out' => '2026-09-01 18:30:00',
            'reason' => '修正申請',
            'status' => 'pending',
        ]);

        AttendanceCorrectionRequestBreak::factory()->create([
            'attendance_correction_request_id' => $request->id,
            'requested_break_in' => '12:00',
            'requested_break_out' => '13:00',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/attendance/detail/'.$request->id);

        $response->assertOk();

        $response->assertViewHas('data', function ($data) {
            return $data['clock_in'] === '09:30'
                && $data['clock_out'] === '18:30'
                && $data['comment'] === '修正申請';
        });
    }

    public function test_admin_can_approve_correction_request(): void
    {
        $user = $this->user();
        $admin = $this->admin();

        $attendance = $this->attendance($user);

        $request = AttendanceCorrectionRequest::factory()->create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_date' => '2026-09-01',
            'requested_clock_in' => '2026-09-01 09:30:00',
            'requested_clock_out' => '2026-09-01 18:30:00',
            'reason' => '修正申請',
            'status' => 'pending',
        ]);

        AttendanceCorrectionRequestBreak::factory()->create([
            'attendance_correction_request_id' => $request->id,
            'requested_break_in' => '12:00',
            'requested_break_out' => '13:00',
        ]);

        $response = $this
            ->actingAs($admin)
            ->post('/stamp_correction_request/approve/'.$request->id);

        $response->assertRedirect(
            '/stamp_correction_request/approve/'.$request->id
        );

        $request->refresh();
        $attendance->refresh();

        $this->assertSame(
            'approved',
            $request->status
        );

        $this->assertSame(
            '2026-09-01 09:30:00',
            $attendance->clock_in->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-09-01 18:30:00',
            $attendance->clock_out->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '修正申請',
            $attendance->comment
        );

        $this->assertDatabaseHas('break_times', [
            'attendance_record_id' => $attendance->id,
            'break_in' => '2026-09-01 12:00:00',
            'break_out' => '2026-09-01 13:00:00',
        ]);
    }
}
