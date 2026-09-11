<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class AttendanceSeeder extends Seeder
{
    /**
     * Create the fixed demo users and their attendance history.
     */
    public function run(): void
    {
        $users = collect([
            ['name' => 'ユーザー1(一般)', 'email' => 'user1@example.com', 'admin_status' => false],
            ['name' => 'ユーザー2(一般)', 'email' => 'user2@example.com', 'admin_status' => false],
            ['name' => 'ユーザー3(管理者)', 'email' => 'user3@example.com', 'admin_status' => true],
        ])->mapWithKeys(function (array $attributes): array {
            $user = User::updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'admin_status' => $attributes['admin_status'],
                    'is_first_login' => false,
                ],
            );

            return [$user->email => $user];
        });

        $user1 = $users->get('user1@example.com');
        $user2 = $users->get('user2@example.com');
        $user3 = $users->get('user3@example.com');

        collect(range(1, 5))->each(function (int $monthsAgo) use ($user1): void {
            $this->createWeekdayRecords(
                $user1,
                Carbon::today()->subMonths($monthsAgo)->startOfMonth(),
                15,
                '09:00:00',
                '18:00:00',
                '通常勤務',
            );
        });

        $currentMonthWeekdays = $this->weekdayDates(Carbon::today()->startOfMonth(), 17);
        $patterns = collect([
            ['clock_in' => '09:00:00', 'clock_out' => '18:00:00', 'comment' => '通常勤務'],
            ['clock_in' => '09:00:00', 'clock_out' => '20:00:00', 'comment' => '残業'],
            ['clock_in' => '09:30:00', 'clock_out' => '18:00:00', 'comment' => '遅刻'],
            ['clock_in' => '09:00:00', 'clock_out' => '17:00:00', 'comment' => '早退'],
            ['clock_in' => '08:00:00', 'clock_out' => '21:00:00', 'comment' => '長時間労働'],
        ]);

        $currentMonthWeekdays->each(function (Carbon $date, int $index) use ($user1, $patterns): void {
            $pattern = match (true) {
                $index < 10 => $patterns->get(0),
                $index < 13 => $patterns->get(1),
                $index < 15 => $patterns->get(2),
                $index === 15 => $patterns->get(3),
                default => $patterns->get(4),
            };

            $this->createRecord($user1, $date, $pattern['clock_in'], $pattern['clock_out'], $pattern['comment']);
        });

        $currentMonthWeekdays->take(5)->each(function (Carbon $date) use ($user2): void {
            $this->createRecord($user2, $date, '09:00:00', '18:00:00', '通常勤務');
        });

        $this->createWeekdayRecords(
            $user3,
            Carbon::today()->subMonth()->startOfMonth(),
            10,
            '09:00:00',
            '18:00:00',
            '管理者勤務',
        );
    }

    /**
     * Create a fixed number of weekday records from a starting month.
     */
    private function createWeekdayRecords(
        User $user,
        Carbon $startDate,
        int $count,
        string $clockIn,
        string $clockOut,
        string $comment,
    ): void {
        $this->weekdayDates($startDate, $count)->each(
            fn (Carbon $date): AttendanceRecord => $this->createRecord($user, $date, $clockIn, $clockOut, $comment),
        );
    }

    /**
     * Return weekday dates, including future weekdays when the month is in progress.
     */
    private function weekdayDates(Carbon $startDate, int $count): Collection
    {
        $dates = collect();
        $date = $startDate->copy();

        while ($dates->count() < $count) {
            if ($date->isWeekday()) {
                $dates->push($date->copy());
            }
            $date->addDay();
        }

        return $dates;
    }

    /**
     * Create or refresh one attendance record and its fixed lunch break.
     */
    private function createRecord(
        User $user,
        Carbon $date,
        string $clockIn,
        string $clockOut,
        string $comment,
    ): AttendanceRecord {
        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date->format('Y-m-d')],
            [
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'comment' => $comment,
            ],
        );

        $record->breakTimes()->delete();
        BreakTime::create([
            'attendance_record_id' => $record->id,
            'break_in' => $date->copy()->setTime(12, 0),
            'break_out' => $date->copy()->setTime(13, 0),
        ]);

        return $record;
    }
}
