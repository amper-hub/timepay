<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->user();
        abort_unless($employee && strtoupper((string) $employee->role) === User::ROLE_EMPLOYEE, 403);

        $leaveRequests = LeaveRequest::query()
            ->with('company')
            ->where('user_id', $employee->id)
            ->latest('start_date')
            ->latest()
            ->paginate(12);

        return view('employee.leaves.index', compact('leaveRequests'));
    }

    public function show(Request $request, LeaveRequest $leaveRequest): View
    {
        $this->authorizeAccess($request, $leaveRequest);
        $leaveRequest->loadMissing(['user.company', 'company']);

        return view('leave-applications.show', [
            'leaveRequest' => $leaveRequest,
            'leaveDays' => $this->calendarLeaveDays($leaveRequest),
        ]);
    }

    public function download(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorizeAccess($request, $leaveRequest);
        $leaveRequest->loadMissing(['user.company', 'company']);

        return Pdf::loadView('leave-applications.pdf', [
            'leaveRequest' => $leaveRequest,
            'leaveDays' => $this->calendarLeaveDays($leaveRequest),
        ])->setPaper('a4', 'portrait')
            ->download('Leave-Application-'.$leaveRequest->id.'.pdf');
    }

    private function authorizeAccess(Request $request, LeaveRequest $leaveRequest): void
    {
        $actor = $request->user();
        $role = strtoupper((string) $actor?->role);

        if ($role === User::ROLE_EMPLOYEE) {
            abort_unless((int) $leaveRequest->user_id === (int) $actor->id, 404);

            return;
        }

        if (in_array($role, [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true)) {
            abort_unless($actor->company_id, 403, 'A company must be assigned to this account.');
            abort_unless((int) $leaveRequest->company_id === (int) $actor->company_id, 404);

            return;
        }

        abort(403);
    }

    private function calendarLeaveDays(LeaveRequest $leaveRequest): int
    {
        return (int) $leaveRequest->start_date->diff($leaveRequest->end_date)->days + 1;
    }
}
