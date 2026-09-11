<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AttendanceRecordController extends Controller
{
    public function index(IndexAttendanceRecordRequest $request)
    {
        $perPage = $request->input('per_page', 20);

        $records = AttendanceRecord::with(['user', 'breakTimes'])
            ->when($request->filled('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $query->whereDate('date', $request->date);
            })
            ->when($request->filled('month'), function ($query) use ($request) {
                $query->where('date', 'like', $request->month.'%');
            })
            ->latest('date')
            ->paginate($perPage);

        return AttendanceRecordResource::collection($records);
    }

    public function show(AttendanceRecord $attendanceRecord)
    {
        $attendanceRecord->load([
            'user',
            'breakTimes',
            'applications',
            'attendanceCorrectionRequest',
        ]);

        $attendanceRecord->showDetails = true;

        return new AttendanceRecordResource($attendanceRecord);
    }

    public function store(StoreAttendanceRecordRequest $request)
    {
        $validated = $request->validated();
        $attendanceRecord = $request->user()->attendance_records()->create($validated);

        $attendanceRecord->load(['user', 'breakTimes']);

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord)
    {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->update($request->validated());

        $attendanceRecord->load(['user', 'breakTimes']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    public function destroy(AttendanceRecord $attendanceRecord)
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'ログイン情報が正しくありません。'], 421);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
