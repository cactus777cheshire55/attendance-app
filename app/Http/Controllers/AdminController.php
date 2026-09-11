<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest as AttendanceCorrectionRequestForm;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $dateString = $request->input('date', Carbon::now()->toDateString());
        $date = Carbon::parse($dateString);
        $previousDay = $date->copy()->subDay()->toDateString();
        $nextDay = $date->copy()->addDay()->toDateString();

        $users = User::where('admin_status', false)->get();

        $attendanceRecords = AttendanceRecord::with('breakTimes')
            ->where('date', $dateString)
            ->get();

        $attendanceRecords->each(function ($record) {
            $record->total_break_time = $record->formatted_total_break_time;
            $record->total_time = $record->formatted_total_time;
        });

        return view('admin.admin-attendance-list', compact('users', 'attendanceRecords', 'date', 'previousDay', 'nextDay'));
    }

    public function adminAttendance($id)
    {
        $user = User::findOrFail($id);
        $dateString = request()->input('date', Carbon::now()->toDateString());
        $date = Carbon::parse($dateString);

        $attendanceRecords = AttendanceRecord::with('breakTimes')
            ->where('user_id', $user->id)
            ->where('date', 'like', $date->format('Y-m').'%')
            ->orderBy('date', 'asc')
            ->get();

        $formattedAttendanceRecords = $attendanceRecords->map(function ($record) {
            return [
                'id' => $record->id,
                'date' => $record->formatted_date,
                'clock_in' => $record->formatted_clock_in,
                'clock_out' => $record->formatted_clock_out,
                'total_break_time' => $record->formatted_total_break_time,
                'total_time' => $record->formatted_total_time,
            ];
        });

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        return view('admin.staff-attendance-list', compact(
            'user',
            'formattedAttendanceRecords',
            'previousMonth',
            'nextMonth',
            'date'
        ));
    }

    public function adminList()
    {
        $users = User::all();

        return view('admin.staff-list', compact('users'));
    }

    public function export(Request $request)
    {
        $userId = $request->input('user_id');
        $yearMonth = $request->input('year_month');

        $user = User::findOrFail($userId);

        $attendanceRecords = AttendanceRecord::with('breakTimes')
            ->where('user_id', $userId)
            ->where('date', 'like', "{$yearMonth}%")
            ->orderBy('date', 'asc')
            ->get();

        return response()->streamDownload(function () use ($attendanceRecords) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '合計']);

            foreach ($attendanceRecords as $record) {
                fputcsv($handle, [
                    $record->formatted_date,
                    $record->formatted_clock_in,
                    $record->formatted_clock_out,
                    $record->formatted_total_break_time,
                    $record->formatted_total_time,
                ]);
            }

            fclose($handle);
        }, $user->name.'_attendance_'.$yearMonth.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function adminDetail($id)
    {
        $record = AttendanceRecord::with(['user', 'breakTimes'])->findOrFail($id);
        $user = $record->user;
        $dateObj = Carbon::parse($record->date);

        $attendanceRecord = [
            'id' => $record->id,
            'year' => $dateObj->format('Y年'),
            'date' => $dateObj->format('n月j日'),
            'clock_in' => $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
            'clock_out' => $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
            'comment' => $record->note ?? $record->comment ?? '',
            'breaks' => $record->breakTimes->map(function ($break) {
                return [
                    'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i') : '',
                    'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
                ];
            })->toArray(),
        ];

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    public function applicationList()
    {
        $rawApplications = AttendanceCorrectionRequest::with(['user', 'attendanceRecord'])
            ->orderBy('created_at', 'desc')
            ->get();

        $applications = $rawApplications->map(function ($app) {
            return new class($app) implements \ArrayAccess
            {
                private $data;

                public function __construct($app)
                {
                    $this->data = [
                        'id' => $app->id,
                        'approval_status' => $app->status === 'approved' ? '承認済み' : '承認待ち',
                        'user' => (object) ['name' => $app->user?->name ?? ''],
                        'AttendanceRecord' => (object) ['date' => $app->requested_date ?? $app->attendanceRecord?->date],
                        'comment' => $app->reason,
                        'application_date' => $app->created_at,
                    ];
                }

                public function __get($key)
                {
                    return $this->data[$key] ?? null;
                }

                public function offsetExists($offset): bool
                {
                    return isset($this->data[$offset]);
                }

                #[\ReturnTypeWillChange]
                public function offsetGet($offset)
                {
                    return $this->data[$offset] ?? null;
                }

                public function offsetSet($offset, $value): void
                {
                    $this->data[$offset] = $value;
                }

                public function offsetUnset($offset): void
                {
                    unset($this->data[$offset]);
                }
            };
        });

        return view('admin.admin-application-list', compact('applications'));
    }

    public function approve($id)
    {
        $application = AttendanceCorrectionRequest::with('breaks')->findOrFail($id);

        return view('admin.admin-application-detail', [
            'application' => $application,
            'user' => $application->user,
        ]);
    }

    public function updateApprove(Request $request, $id)
    {
        DB::transaction(function () use ($id) {
            $application = AttendanceCorrectionRequest::with('breaks')
                ->findOrFail($id);

            $application->status = 'approved';
            $application->save();

            $attendance = AttendanceRecord::find($application->attendance_record_id);

            if (! $attendance) {
                return;
            }

            if ($application->requested_clock_in) {
                $attendance->clock_in = $application->requested_clock_in;
            }

            if ($application->requested_clock_out) {
                $attendance->clock_out = $application->requested_clock_out;
            }

            if ($application->reason) {
                $attendance->comment = $application->reason;
            }

            $attendance->save();

            if ($application->breaks->isNotEmpty()) {
                $attendance->breakTimes()->delete();

                foreach ($application->breaks as $break) {
                    $attendance->breakTimes()->create([
                        'break_in' => $break->requested_break_in,
                        'break_out' => $break->requested_break_out,
                    ]);
                }
            }
        });

        return redirect()
            ->to('/stamp_correction_request/approve/'.$id);
    }

    public function adminUpdateDetail(AttendanceCorrectionRequestForm $request, $id)
    {
        $attendance = AttendanceRecord::with('breakTimes')
            ->findOrFail($id);

        $date = Carbon::parse($attendance->date)->format('Y-m-d');

        $clockIn = $request->input('new_clock_in');
        $clockOut = $request->input('new_clock_out');

        if ($clockIn && $clockOut && $clockIn >= $clockOut) {
            return back()
                ->withErrors([
                    'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
                ])
                ->withInput();
        }

        $breakIns = $request->input('new_break_in', []);
        $breakOuts = $request->input('new_break_out', []);

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            if (! $breakIn && ! $breakOut) {
                continue;
            }

            if ($clockIn && $breakIn && $breakIn <= $clockIn) {
                return back()
                    ->withErrors([
                        "new_break_in.$index" => '休憩時間もしくは出退勤時間が不適切な値です',
                    ])
                    ->withInput();
            }

            if ($clockOut && $breakIn && $breakIn >= $clockOut) {
                return back()
                    ->withErrors([
                        "new_break_in.$index" => '休憩時間もしくは退勤時間が不適切な値です',
                    ])
                    ->withInput();
            }

            if ($clockOut && $breakOut && $breakOut >= $clockOut) {
                return back()
                    ->withErrors([
                        "new_break_out.$index" => '休憩時間もしくは退勤時間が不適切な値です',
                    ])
                    ->withInput();
            }

            if ($breakIn && $breakOut && $breakIn >= $breakOut) {
                return back()
                    ->withErrors([
                        "new_break_out.$index" => '休憩時間もしくは退勤時間が不適切な値です',
                    ])
                    ->withInput();
            }
        }

        DB::transaction(function () use (
            $attendance,
            $date,
            $clockIn,
            $clockOut,
            $breakIns,
            $breakOuts,
            $request
        ) {
            $attendance->update([
                'clock_in' => $clockIn
                    ? $date.' '.$clockIn.':00'
                    : null,

                'clock_out' => $clockOut
                    ? $date.' '.$clockOut.':00'
                    : null,

                'comment' => $request->input('comment'),
            ]);

            $attendance->breakTimes()->delete();

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (! $breakIn && ! $breakOut) {
                    continue;
                }

                $attendance->breakTimes()->create([
                    'break_in' => $breakIn
                        ? $date.' '.$breakIn.':00'
                        : null,

                    'break_out' => $breakOut
                        ? $date.' '.$breakOut.':00'
                        : null,
                ]);
            }
        });

        return redirect('/admin/attendance/'.$attendance->id);
    }
}
