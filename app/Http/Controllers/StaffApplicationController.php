<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionRequest;
use Carbon\Carbon;

class StaffApplicationController extends Controller
{
    public function application()
    {
        $user = auth()->user();

        $applications = AttendanceCorrectionRequest::with(['breaks', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedApplications = $applications->map(function ($app) {
            return [
                'id' => $app->id,
                'approval_status' => $app->status === 'approved' ? '承認済み' : '承認待ち',
                'date' => Carbon::parse($app->requested_date)->format('Y/m/d'),
                'comment' => $app->reason,
                'application_date' => $app->created_at->format('Y/m/d'),
                'clock_in' => Carbon::parse($app->requested_clock_in)->format('H:i'),
                'clock_out' => Carbon::parse($app->requested_clock_out)->format('H:i'),
                'breaks' => $app->breaks->map(function ($break) use ($app) {
                    return [
                        'id' => $break->id,
                        'break_in' => $break->requested_break_in ? Carbon::parse($break->requested_break_in)->format('H:i') : '',
                        'break_out' => $break->requested_break_out ? Carbon::parse($break->requested_break_out)->format('H:i') : '',
                        'status' => $app->status,
                        'user_id' => $app->user_id,
                        'user_name' => $app->user?->name,
                    ];
                })->toArray(),
            ];
        });

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }

    public function showApplication($id)
    {
        $correctionRequest = AttendanceCorrectionRequest::with(['breaks', 'user', 'attendanceRecord'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $user = $correctionRequest->user;
        $dateObj = $correctionRequest->requested_date ? Carbon::parse($correctionRequest->requested_date) : null;

        $breaks = $correctionRequest->breaks->map(function ($break) {
            return [
                'break_in' => $break->requested_break_in ? Carbon::parse($break->requested_break_in)->format('H:i') : '',
                'break_out' => $break->requested_break_out ? Carbon::parse($break->requested_break_out)->format('H:i') : '',
            ];
        })->toArray();

        if (empty($breaks)) {
            $breaks = [
                ['break_in' => '', 'break_out' => ''],
            ];
        }

        $data = [
            'id' => $correctionRequest->attendance_record_id,
            'year' => $dateObj ? $dateObj->format('Y年') : '',
            'date' => $dateObj ? $dateObj->format('n月j日') : '',
            'clock_in' => $correctionRequest->requested_clock_in ? Carbon::parse($correctionRequest->requested_clock_in)->format('H:i') : '',
            'clock_out' => $correctionRequest->requested_clock_out ? Carbon::parse($correctionRequest->requested_clock_out)->format('H:i') : '',
            'breaks' => $breaks,
            'comment' => $correctionRequest->reason ?? '',
            'application' => $correctionRequest,
        ];

        return view('user.user-detail', compact('data', 'user'));
    }
}
