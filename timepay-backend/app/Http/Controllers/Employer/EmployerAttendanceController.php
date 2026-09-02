<?php

namespace App\Http\Controllers\Employer;

use App\Events\AttendanceRejected;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployerAttendanceController extends Controller
{
    /**
     * Display the attendance logs for the company.
     */
    public function index(): View
    {
        $companyId = auth()->user()->company_id;

        $attendanceLogs = AttendanceLog::where('company_id', $companyId)
            ->with('user')
            ->latest('timestamp')
            ->paginate(15);

        return view('employer.attendance', [
            'attendanceLogs' => $attendanceLogs,
        ]);
    }

    /**
     * Manually reject a suspicious or invalid attendance entry.
     */
    public function reject(Request $request, AttendanceLog $attendanceLog): RedirectResponse|JsonResponse
    {
        abort_unless($attendanceLog->company_id === auth()->user()->company_id, 403);

        $activeShiftTerminated = DB::transaction(function () use ($attendanceLog): bool {
            $lockedLog = AttendanceLog::query()
                ->whereKey($attendanceLog->id)
                ->lockForUpdate()
                ->firstOrFail();

            $latestPunch = AttendanceLog::query()
                ->where('company_id', $lockedLog->company_id)
                ->where('user_id', $lockedLog->user_id)
                ->whereDate('timestamp', $lockedLog->timestamp->toDateString())
                ->latest('timestamp')
                ->lockForUpdate()
                ->first();

            $terminatesActiveShift = $latestPunch?->id === $lockedLog->id
                && $lockedLog->type === 'clock_in'
                && $lockedLog->status !== 'rejected';

            $lockedLog->update([
                'status' => 'rejected',
                'is_suspicious' => true,
                'suspicion_reason' => $lockedLog->suspicion_reason ?: 'Manually rejected by employer',
            ]);

            $attendanceLog->setRawAttributes($lockedLog->fresh()->getAttributes(), true);

            return $terminatesActiveShift;
        });

        if ($activeShiftTerminated) {
            AttendanceRejected::dispatch($attendanceLog);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $activeShiftTerminated
                    ? 'Attendance entry rejected and active shift ended.'
                    : 'Attendance entry rejected.',
                'active_shift_terminated' => $activeShiftTerminated,
                'attendance_log' => [
                    'id' => $attendanceLog->id,
                    'status' => $attendanceLog->status,
                    'is_suspicious' => $attendanceLog->is_suspicious,
                    'suspicion_reason' => $attendanceLog->suspicion_reason,
                ],
            ]);
        }

        return back()->with(
            'status',
            $activeShiftTerminated
                ? 'Attendance entry rejected and active shift ended.'
                : 'Attendance entry rejected.'
        );
    }
}
