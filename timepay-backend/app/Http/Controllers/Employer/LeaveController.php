<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeaveController extends Controller
{
    /**
     * Display leave requests for the authenticated employer's company.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        $leaveRequests = LeaveRequest::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->latest('start_date')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('employer.leaves.index', [
            'leaveRequests' => $leaveRequests,
            'selectedStatus' => $request->string('status')->toString(),
        ]);
    }

    /**
     * Approve or decline a company leave request.
     */
    public function update(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->company_id === $request->user()->company_id, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $leaveRequest->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return back()->with('success', 'Leave request updated successfully.');
    }
}
