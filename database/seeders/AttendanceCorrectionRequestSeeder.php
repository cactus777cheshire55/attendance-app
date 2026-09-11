<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceCorrectionRequestSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $record = AttendanceRecord::where('user_id', $user1->id)->first();

        if ($record) {
            $dateStr = $record->date;

            AttendanceCorrectionRequest::create([
                'attendance_record_id' => $record->id,
                'user_id' => $user1->id,
                'requested_date' => $dateStr,
                'requested_clock_in' => Carbon::parse($dateStr)->setTime(9, 0, 0),
                'requested_clock_out' => Carbon::parse($dateStr)->setTime(18, 30, 0),
                'reason' => '残業時間の入力漏れのため修正を申請します。',
                'status' => 'pending',
            ]);

            $oldRecord = AttendanceRecord::where('user_id', $user1->id)->skip(1)->first();
            if ($oldRecord) {
                $oldDateStr = $oldRecord->date;

                AttendanceCorrectionRequest::create([
                    'attendance_record_id' => $oldRecord->id,
                    'user_id' => $user1->id,
                    'requested_date' => $oldDateStr,
                    'requested_clock_in' => Carbon::parse($oldDateStr)->setTime(8, 55, 0),
                    'requested_clock_out' => Carbon::parse($oldDateStr)->setTime(18, 5, 0),
                    'reason' => '打刻ミス修正済み。',
                    'status' => 'approved',
                ]);
            }
        }
    }
}
