<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest as AttendanceCorrectionRequestForm;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function attendance()
    {
        $user = Auth::user();

        $now = Carbon::now();
        $formattedDate = $now->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = $now->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    public function attendanceStatus(Request $request)
    {
        $status = $request->input('action');
        $userId = Auth::id();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();
        $attendance = AttendanceRecord::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        switch ($status) {
            case 'clock_in':
                if (! $attendance) {
                    AttendanceRecord::create([
                        'user_id' => $userId,
                        'date' => $today,
                        'clock_in' => $now,
                    ]);
                }
                break;

            case 'break_in':
                if ($attendance && ! $attendance->clock_out
                    && ! $attendance->breakTimes()->whereNull('break_out')->exists()) {
                    $attendance->breakTimes()->create([
                        'break_in' => $now,
                    ]);
                }
                break;

            case 'break_out':
                if ($attendance && ! $attendance->clock_out) {
                    $latestBreak = $attendance->breakTimes()
                        ->whereNull('break_out')
                        ->latest()
                        ->first();

                    $latestBreak?->update([
                        'break_out' => $now,
                    ]);
                }
                break;

            case 'clock_out':
                if ($attendance && ! $attendance->clock_out
                    && ! $attendance->breakTimes()->whereNull('break_out')->exists()) {
                    $attendance->update([
                        'clock_out' => $now,
                    ]);
                }
                break;

            default:
                break;
        }

        return redirect()->back();
    }

    public function staffList(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()));
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $daysInMonth = $date->daysInMonth;
        $monthDates = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $monthDates[] = $date->copy()->day($i);
        }

        $attendanceRecords = AttendanceRecord::with('breakTimes')
            ->where('user_id', Auth::id())
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->get()
            ->keyBy(function ($record) {
                return Carbon::parse($record->date)->format('Y-m-d');
            });

        $formattedAttendanceRecords = collect($monthDates)->map(function ($currentDate) use ($attendanceRecords) {
            $dateKey = $currentDate->format('Y-m-d');
            $record = $attendanceRecords->get($dateKey);

            if ($record) {
                return [
                    'id' => $record->id,
                    'date' => $currentDate->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    'clock_out' => $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    'total_break_time' => $record->formatted_total_break_time,
                    'total_time' => $record->formatted_total_time,
                    'comment' => $record->comment,
                ];
            } else {
                return [
                    'id' => 'create?date='.$dateKey,
                    'date' => $currentDate->isoFormat('MM/DD(ddd)'),
                    'clock_in' => '',
                    'clock_out' => '',
                    'total_break_time' => '',
                    'total_time' => '',
                    'comment' => '',
                ];
            }
        });

        return view('user.user-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    public function show(Request $request, $id)
    {
        if ($id === 'create') {
            $dateString = $request->query('date');
            $dateObj = Carbon::parse($dateString);
            $user = Auth::user();

            $data = [
                'id' => 'create?date='.$dateString,
                'year' => $dateObj->format('Y年'),
                'date' => $dateObj->format('n月j日'),
                'clock_in' => '',
                'clock_out' => '',
                'breaks' => [
                    ['break_in' => '', 'break_out' => ''],
                ],
                'comment' => '',
                'application' => null,
            ];

            $attendanceRecord = new AttendanceRecord;

            return view('user.user-detail', compact('data', 'user', 'attendanceRecord'));
        }

        $attendanceRecord = AttendanceRecord::with(['user', 'breakTimes', 'attendanceCorrectionRequest'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $correctionRequest = $attendanceRecord->attendanceCorrectionRequest;
        $application = ($correctionRequest && $correctionRequest->status === 'pending') ? $correctionRequest : null;

        $dateObj = $attendanceRecord->date ? Carbon::parse($attendanceRecord->date) : null;

        $breaks = $attendanceRecord->breakTimes->map(function ($break) {
            return [
                'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i') : '',
                'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
            ];
        })->toArray();

        if (empty($breaks)) {
            $breaks = [
                ['break_in' => '', 'break_out' => ''],
            ];
        }

        $data = [
            'id' => $attendanceRecord->id,
            'year' => $dateObj ? $dateObj->format('Y年') : '',
            'date' => $dateObj ? $dateObj->format('n月j日') : '',
            'clock_in' => $attendanceRecord->clock_in ? Carbon::parse($attendanceRecord->clock_in)->format('H:i') : '',
            'clock_out' => $attendanceRecord->clock_out ? Carbon::parse($attendanceRecord->clock_out)->format('H:i') : '',
            'breaks' => $breaks,
            'comment' => $attendanceRecord->comment ?? '',
            'application' => $application,
        ];

        $user = $attendanceRecord->user;

        return view('user.user-detail', compact('data', 'user', 'attendanceRecord'));
    }

    public function applicationUpdate(AttendanceCorrectionRequestForm $request, $id)
    {
        if ($id === 'create') {
            $requestedDate = $request->query('date');

            DB::transaction(function () use ($request, $requestedDate) {

                $clockIn = $request->input('new_clock_in');
                $clockOut = $request->input('new_clock_out');

                $attendance = AttendanceRecord::create([
                    'user_id' => auth()->id(),
                    'date' => $requestedDate,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                ]);

                $correctionRequest = AttendanceCorrectionRequest::create([
                    'attendance_record_id' => $attendance->id,
                    'user_id' => auth()->id(),
                    'requested_date' => $requestedDate,
                    'requested_clock_in' => $clockIn ? $requestedDate.' '.$clockIn.':00' : null,
                    'requested_clock_out' => $clockOut ? $requestedDate.' '.$clockOut.':00' : null,
                    'reason' => $request->input('comment'),
                    'status' => 'pending',
                ]);

                $breakIns = $request->input('new_break_in', []);
                $breakOuts = $request->input('new_break_out', []);

                foreach ($breakIns as $index => $breakIn) {
                    $breakOut = $breakOuts[$index] ?? null;

                    if (empty($breakIn) && empty($breakOut)) {
                        continue;
                    }

                    $correctionRequest->breaks()->create([
                        'requested_break_in' => $breakIn ? $requestedDate.' '.$breakIn.':00' : null,
                        'requested_break_out' => $breakOut ? $requestedDate.' '.$breakOut.':00' : null,
                    ]);
                }
            });

            return redirect('/attendance')->with('success', '修正申請を送信しました。');
        }

        $attendance = AttendanceRecord::where('user_id', auth()->id())
            ->findOrFail($id);

        $pendingApplication = AttendanceCorrectionRequest::where('attendance_record_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingApplication) {
            return redirect()->back()->with('error', 'すでに修正申請中です。');
        }

        DB::transaction(function () use ($request, $attendance) {
            $requestedDate = Carbon::parse($attendance->date)->format('Y-m-d');
            $clockIn = $request->input('new_clock_in');
            $clockOut = $request->input('new_clock_out');

            $correctionRequest = AttendanceCorrectionRequest::create([
                'attendance_record_id' => $attendance->id,
                'user_id' => auth()->id(),
                'requested_date' => $requestedDate,
                'requested_clock_in' => $clockIn ? $requestedDate.' '.$clockIn.':00' : null,
                'requested_clock_out' => $clockOut ? $requestedDate.' '.$clockOut.':00' : null,
                'reason' => $request->input('comment'),
                'status' => 'pending',
            ]);

            $breakIns = $request->input('new_break_in', []);
            $breakOuts = $request->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (empty($breakIn) && empty($breakOut)) {
                    continue;
                }

                $correctionRequest->breaks()->create([
                    'requested_break_in' => $breakIn ? $requestedDate.' '.$breakIn.':00' : null,
                    'requested_break_out' => $breakOut ? $requestedDate.' '.$breakOut.':00' : null,
                ]);
            }
        });

        return redirect()->back()->with('success', '修正申請を送信しました。');
    }
}
