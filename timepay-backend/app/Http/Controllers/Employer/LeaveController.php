<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
        $this->authorizeEmployer($request);
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);

        $leaveRequests = LeaveRequest::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->when(! empty($validated['status']), function ($query) use ($validated): void {
                $query->where('status', $validated['status']);
            })
            ->latest('start_date')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('employer.leaves.index', [
            'leaveRequests' => $leaveRequests,
            'selectedStatus' => $validated['status'] ?? '',
            'pendingCount' => LeaveRequest::query()->where('company_id', $companyId)->where('status', 'pending')->count(),
        ]);
    }

    /**
     * Approve or decline a company leave request.
     */
    public function update(Request $request, LeaveRequest $leaveRequest): RedirectResponse|JsonResponse
    {
        $this->authorizeEmployer($request);
        $companyId = (int) $request->user()->company_id;
        abort_unless((int) $leaveRequest->company_id === $companyId, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = DB::transaction(function () use ($leaveRequest, $companyId, $validated): array {
            $current = LeaveRequest::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($leaveRequest->id);

            if ($current->status !== 'pending') {
                return ['conflict' => true, 'leave' => $current];
            }

            $current->update([
                'status' => $validated['status'],
                'admin_notes' => $validated['admin_notes'] ?? $current->admin_notes,
            ]);

            return ['conflict' => false, 'leave' => $current->refresh()];
        });

        /** @var LeaveRequest $current */
        $current = $result['leave'];
        $data = [
            'id' => $current->id,
            'status' => $current->status,
            'admin_notes' => $current->admin_notes,
            'updated_at' => $current->updated_at?->toIso8601String(),
        ];

        if ($result['conflict']) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This request has already been reviewed. Refresh to see its current status.', 'data' => $data], 409);
            }

            return back()->withErrors(['status' => 'This request has already been reviewed. Refresh to see its current status.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Leave request updated successfully.', 'data' => $data]);
        }

        return back()->with('success', 'Leave request updated successfully.');
    }

    private function authorizeEmployer(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor && in_array(strtoupper((string) $actor->role), [User::ROLE_EMPLOYER, User::ROLE_ADMIN], true), 403);
        abort_unless($actor->company_id, 403, 'A company must be assigned to this account.');
    }
}
