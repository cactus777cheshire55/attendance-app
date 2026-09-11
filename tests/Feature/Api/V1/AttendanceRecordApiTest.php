<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一覧取得
     *
     * 未認証でも取得できる。
     * month / per_page が機能する。
     * 一覧には breaks を含めない。
     */
    public function test_index_is_public_and_returns_paginated_records_without_breaks(): void
    {
        $user = User::factory()->create([
            'name' => '山田太郎',
        ]);

        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        BreakTime::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?month=2026-05&per_page=1'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $record->id)
            ->assertJsonPath('data.0.user_id', $user->id)
            ->assertJsonPath('data.0.user.name', '山田太郎')
            ->assertJsonPath('data.0.date', '2026-05-01')
            ->assertJsonPath('data.0.clock_in', '09:00:00')
            ->assertJsonPath('data.0.clock_out', '18:00:00')
            ->assertJsonPath('data.0.total_time', '8:00')
            ->assertJsonPath('data.0.total_break_time', '01:00')
            ->assertJsonPath('data.0.comment', '通常勤務')
            ->assertJsonPath('meta.per_page', 1);

        $this->assertArrayNotHasKey(
            'breaks',
            $response->json('data.0')
        );
    }

    /**
     * 一覧取得
     *
     * user_id で絞り込みできる。
     */
    public function test_index_can_filter_by_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $record1 = AttendanceRecord::factory()->create([
            'user_id' => $user1->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $user2->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?user_id='.$user1->id
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $record1->id)
            ->assertJsonPath('data.0.user_id', $user1->id);
    }

    /**
     * 一覧取得
     *
     * date で絞り込みできる。
     */
    public function test_index_can_filter_by_date(): void
    {
        $user = User::factory()->create();

        $target = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-16',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?date=2026-05-15'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('data.0.date', '2026-05-15');
    }

    /**
     * 詳細取得
     *
     * 未認証でも取得できる。
     * user / breakTimes がロードされる。
     */
    public function test_show_is_public_and_includes_related_data(): void
    {
        $user = User::factory()->create([
            'name' => '山田太郎',
        ]);

        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        BreakTime::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records/'.$record->id
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', '山田太郎')
            ->assertJsonPath('data.date', '2026-05-01')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonPath('data.total_time', '8:00')
            ->assertJsonPath('data.total_break_time', '01:00')
            ->assertJsonPath('data.comment', '通常勤務')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'user' => [
                        'id',
                        'name',
                    ],
                    'date',
                    'clock_in',
                    'clock_out',
                    'total_time',
                    'total_break_time',
                    'comment',
                    'breaks',
                    'applications',
                ],
            ]);

        $response->assertJsonPath('data.breaks.0.break_in', '12:00:00');
        $response->assertJsonPath('data.breaks.0.break_out', '13:00:00');
    }

    /**
     * 存在しない勤怠を取得した場合、404になる。
     */
    public function test_show_returns_not_found_for_nonexistent_record(): void
    {
        $this->getJson('/api/v1/attendance-records/999999')
            ->assertNotFound();
    }

    /**
     * 登録
     *
     * Sanctum認証が必要。
     */
    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ])->assertUnauthorized();
    }

    /**
     * 登録
     *
     * 認証済みユーザーが自分の勤怠を登録できる。
     */
    public function test_store_creates_attendance_record_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.date', '2026-05-01')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonPath('data.comment', '通常勤務');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);
    }

    /**
     * 登録
     *
     * バリデーションエラーの場合、日本語メッセージが返る。
     */
    public function test_store_returns_japanese_validation_messages(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026/05/01',
            'clock_in' => '09:00',
            'clock_out' => '08:00:00',
            'comment' => str_repeat('あ', 256),
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
                'clock_in' => '出勤時刻は HH:MM:SS 形式で指定してください。',
                'clock_out' => '退勤時刻は出勤時刻より後の時刻を指定してください。',
                'comment' => '備考は 255 文字以内で入力してください。',
            ]);
    }

    /**
     * 登録
     *
     * 同一ユーザー・同一日付の重複登録はできない。
     */
    public function test_store_rejects_duplicate_date_for_same_user(): void
    {
        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-01',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date' => 'この日付の勤怠は既に登録されています。',
            ]);
    }

    /**
     * 更新
     *
     * 未認証では更新できない。
     */
    public function test_update_requires_authentication(): void
    {
        $record = AttendanceRecord::factory()->create([
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->putJson(
            '/api/v1/attendance-records/'.$record->id,
            [
                'date' => '2026-05-01',
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
            ]
        )->assertUnauthorized();
    }

    /**
     * 更新
     *
     * 本人は自分の勤怠を更新できる。
     */
    public function test_owner_can_update_own_record(): void
    {
        $owner = User::factory()->create();

        $record = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson(
            '/api/v1/attendance-records/'.$record->id,
            [
                'date' => '2026-05-01',
                'clock_in' => '09:30:00',
                'clock_out' => '18:30:00',
                'comment' => '変更後',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.clock_in', '09:30:00')
            ->assertJsonPath('data.clock_out', '18:30:00')
            ->assertJsonPath('data.comment', '変更後');

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => '変更後',
        ]);
    }

    /**
     * 更新
     *
     * 他ユーザーは更新できない。
     */
    public function test_other_user_cannot_update_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $record = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前',
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->putJson(
            '/api/v1/attendance-records/'.$record->id,
            [
                'date' => '2026-05-01',
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
                'comment' => '不正更新',
            ]
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'comment' => '変更前',
        ]);
    }

    /**
     * 更新
     *
     * 管理者は他ユーザーの勤怠も更新できる。
     */
    public function test_admin_can_update_any_record(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $owner = User::factory()->create();

        $record = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson(
            '/api/v1/attendance-records/'.$record->id,
            [
                'date' => '2026-05-01',
                'clock_in' => '08:30:00',
                'clock_out' => '17:30:00',
                'comment' => '管理者修正',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.clock_in', '08:30:00')
            ->assertJsonPath('data.clock_out', '17:30:00')
            ->assertJsonPath('data.comment', '管理者修正');
    }

    /**
     * 更新
     *
     * 退勤時刻が出勤時刻以前の場合はバリデーションエラー。
     */
    public function test_update_rejects_invalid_clock_out(): void
    {
        $owner = User::factory()->create();

        $record = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson(
            '/api/v1/attendance-records/'.$record->id,
            [
                'date' => '2026-05-01',
                'clock_in' => '09:00:00',
                'clock_out' => '08:00:00',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'clock_out' => '退勤時刻は出勤時刻より後の時刻を指定してください。',
            ]);
    }

    /**
     * 削除
     *
     * 未認証では削除できない。
     */
    public function test_delete_requires_authentication(): void
    {
        $record = AttendanceRecord::factory()->create();

        $this->deleteJson(
            '/api/v1/attendance-records/'.$record->id
        )->assertUnauthorized();

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
        ]);
    }

    /**
     * 削除
     *
     * 本人は自分の勤怠を削除できる。
     */
    public function test_owner_can_delete_own_record(): void
    {
        $owner = User::factory()->create();

        $record = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson(
            '/api/v1/attendance-records/'.$record->id
        )->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $record->id,
        ]);
    }

    /**
     * 削除
     *
     * 他ユーザーは削除できない。
     */
    public function test_other_user_cannot_delete_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $record = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson(
            '/api/v1/attendance-records/'.$record->id
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
        ]);
    }

    /**
     * 削除
     *
     * 管理者は他ユーザーの勤怠も削除できる。
     */
    public function test_admin_can_delete_any_record(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $owner = User::factory()->create();

        $record = AttendanceRecord::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson(
            '/api/v1/attendance-records/'.$record->id
        )->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $record->id,
        ]);
    }
}
