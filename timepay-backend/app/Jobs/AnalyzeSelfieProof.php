<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Services\FacePlusPlusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeSelfieProof implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $attendanceLogId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(FacePlusPlusService $facePlusPlusService): void
    {
        $attendanceLog = AttendanceLog::query()->find($this->attendanceLogId);

        if (! $attendanceLog?->photo_path) {
            return;
        }

        $photoPath = Storage::disk('public')->path($attendanceLog->photo_path);

        if (! is_file($photoPath)) {
            Log::warning('Selfie audit skipped because the stored photo is missing.', [
                'attendance_log_id' => $attendanceLog->id,
                'photo_path' => $attendanceLog->photo_path,
            ]);

            return;
        }

        $analysis = $facePlusPlusService->analyzeSelfieProof($photoPath);

        if ($analysis['suspicious'] ?? false) {
            $attendanceLog->forceFill([
                'is_suspicious' => true,
                'suspicion_reason' => $analysis['reason'] ?? 'Face++ flagged low quality/potential spoof',
            ])->save();
        }
    }
}
