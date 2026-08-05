<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveAuditController extends Controller
{
    /**
     * Display a read-only, system-wide leave request audit.
     */
    public function index(Request $request): View
    {
        $leaveRequests = LeaveRequest::query()
            ->withoutGlobalScopes()
            ->with(['company', 'user'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->latest('start_date')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('super-admin.leaves.index', [
            'leaveRequests' => $leaveRequests,
            'selectedStatus' => $request->string('status')->toString(),
        ]);
    }
}
