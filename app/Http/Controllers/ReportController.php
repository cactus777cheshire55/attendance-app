<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function report()
    {
        $user = auth()->user();
        $currentMonth = Carbon::now()->startOfMonth();
        $sixMonthsAgo = $currentMonth->copy()->subMonths(5);

        $records = AttendanceRecord::with('breakTimes')
            ->where('user_id', $user->id)
            ->whereDate('date', '>=', $sixMonthsAgo)
            ->whereNotNull('clock_out')
            ->get();

        $totalWorkMinutesSum = 0;
        $totalOvertimeMinutesSum = 0;
        $workDayCount = 0;

        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        $monthlyData = [];
        for ($month = $sixMonthsAgo->copy(); $month <= $currentMonth; $month->addMonth()) {
            $monthKey = $month->format('Y年m月');
            $monthlyData[$monthKey] = [
                'month' => $monthKey,
                'work_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        foreach ($records as $record) {
            $clockIn = Carbon::parse($record->clock_in);
            $clockOut = Carbon::parse($record->clock_out);

            if ($clockOut->lt($clockIn)) {
                $clockOut->addDay();
            }

            $breakMinutes = 0;
            foreach ($record->breakTimes as $break) {
                if ($break->break_in && $break->break_out) {
                    $bIn = Carbon::parse($break->break_in);
                    $bOut = Carbon::parse($break->break_out);

                    if ($bOut->lt($bIn)) {
                        $bOut->addDay();
                    }
                    $breakMinutes += $bOut->diffInMinutes($bIn);
                }
            }

            $grossMinutes = $clockOut->diffInMinutes($clockIn);
            $dailyWorkMinutes = max(0, $grossMinutes - $breakMinutes);

            $totalWorkMinutesSum += $dailyWorkMinutes;
            $workDayCount++;

            $dailyOvertime = $dailyWorkMinutes > 480 ? ($dailyWorkMinutes - 480) : 0;
            $totalOvertimeMinutesSum += $dailyOvertime;

            $monthKey = Carbon::parse($record->date)->format('Y年m月');
            $monthlyData[$monthKey]['work_minutes'] += $dailyWorkMinutes;
            $monthlyData[$monthKey]['overtime_minutes'] += $dailyOvertime;

            if (Carbon::parse($record->date)->isSameMonth($currentMonth)) {
                if (Carbon::parse($record->clock_in)->format('H:i:s') > '09:00:00') {
                    $lateCount++;
                }

                if (Carbon::parse($record->clock_out)->format('H:i:s') < '18:00:00') {
                    $earlyLeaveCount++;
                }

                if ($dailyWorkMinutes > 600) {
                    $longWorkCount++;
                }
            }
        }

        $avgWorkMinutes = $workDayCount > 0 ? ($totalWorkMinutesSum / $workDayCount) : 0;

        $summary = [
            'total_work_minutes' => $totalWorkMinutesSum,
            'total_overtime_minutes' => $totalOvertimeMinutesSum,
            'avg_work_minutes' => $avgWorkMinutes,
        ];

        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        $monthlyTrend = array_values($monthlyData);

        return view('reports.index', compact('summary', 'anomalies', 'monthlyTrend'));
    }
}
