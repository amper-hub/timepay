<?php

namespace App\Events;

use App\Models\AttendanceLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $attendanceLogId;

    public int $employeeId;

    public string $reason;

    public string $terminatedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(AttendanceLog $attendanceLog)
    {
        $this->attendanceLogId = $attendanceLog->id;
        $this->employeeId = $attendanceLog->user_id;
        $this->reason = $attendanceLog->suspicion_reason ?: 'Your shift was terminated. The employer rejected your clock-in photo.';
        $this->terminatedAt = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('employee.' . $this->employeeId),
        ];
    }

    /**
     * The event alias consumed by Laravel Echo clients.
     */
    public function broadcastAs(): string
    {
        return 'AttendanceRejected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'attendance_log_id' => $this->attendanceLogId,
            'employee_id' => $this->employeeId,
            'reason' => $this->reason,
            'terminated_at' => $this->terminatedAt,
        ];
    }
}
