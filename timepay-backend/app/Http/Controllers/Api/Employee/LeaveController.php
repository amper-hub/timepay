<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
