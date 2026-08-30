<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    /**
     * Display the authenticated employee's leave request history.
     */
    public function index(Request $request): JsonResponse
    {
        $leaves = LeaveRequest::query()
            ->where('user_id', $request->user()->id)
            ->latest('start_date')
            ->latest()
            ->get();

        return response()->json([
            'data' => $leaves,
        ]);
    }

    /**
     * Return the authenticated employee's current-month leave balances.
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();

        $sickLeaveUsed = $this->approvedLeaveDaysUsedThisMonth(
            $user->id,
            'Sick',
            $monthStart,
            $monthEnd
        );

        $vacationLeaveUsed = $this->approvedLeaveDaysUsedThisMonth(
            $user->id,
            'Vacation',
            $monthStart,
            $monthEnd
        );

        $sickLeaveLimit = (int) ($company?->monthly_sick_leave_limit ?? 2);
        $vacationLeaveLimit = (int) ($company?->monthly_vacation_leave_limit ?? 2);

        return response()->json([
            'sick_leave_limit' => $sickLeaveLimit,
            'sick_leave_used_this_month' => $sickLeaveUsed,
            'sick_leave_remaining' => max(0, $sickLeaveLimit - $sickLeaveUsed),
            'vacation_leave_limit' => $vacationLeaveLimit,
            'vacation_leave_used_this_month' => $vacationLeaveUsed,
            'vacation_leave_remaining' => max(0, $vacationLeaveLimit - $vacationLeaveUsed),
            'current_month_name' => $monthStart->format('F Y'),
        ]);
    }

    /**
     * Store a newly submitted employee leave request.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type' => ['required', 'string', Rule::in(['Sick', 'Vacation', 'Emergency', 'Unpaid'])],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $user = $request->user();

        if (in_array($validated['leave_type'], ['Sick', 'Vacation'], true)) {
            $remaining = $this->remainingLeaveDaysForType($user->id, $validated['leave_type']);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'leave_type' => "You have no {$validated['leave_type']} leave days remaining for this month.",
                ]);
            }
        }

        $leave = LeaveRequest::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data' => $leave,
        ], 201);
    }

    private function approvedLeaveDaysUsedThisMonth(
        int $userId,
        string $leaveType,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd
    ): int {
        return LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('leave_type', $leaveType)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->get(['start_date', 'end_date'])
            ->sum(function (LeaveRequest $leaveRequest) use ($monthStart, $monthEnd): int {
                $startDate = CarbonImmutable::parse($leaveRequest->start_date)->max($monthStart);
                $endDate = CarbonImmutable::parse($leaveRequest->end_date)->min($monthEnd);

                return (int) $startDate->diffInDays($endDate) + 1;
            });
    }

    private function remainingLeaveDaysForType(int $userId, string $leaveType): int
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $monthEnd = CarbonImmutable::now()->endOfMonth();
        $user = request()->user();
        $company = $user?->company;

        $limit = match ($leaveType) {
            'Sick' => (int) ($company?->monthly_sick_leave_limit ?? 2),
            'Vacation' => (int) ($company?->monthly_vacation_leave_limit ?? 2),
            default => 0,
        };

        $used = $this->approvedLeaveDaysUsedThisMonth(
            $userId,
            $leaveType,
            $monthStart,
            $monthEnd
        );

        return max(0, $limit - $used);
    }
}
