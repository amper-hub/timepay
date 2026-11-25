<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\PayrollProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoClockOutEmployees extends Command
{
    protected $signature = 'attendance:auto-clock-out';

    protected $description = 'Automatically clock out employees without overtime eligibility at their scheduled shift end';

    public function handle(): int
    {
        $timezone = config('timepay.attendance_timezone', config('app.timezone', 'UTC'));
        $now = Carbon::now($timezone);
        $closedCount = 0;

        Company::query()
            ->whereNotNull('work_start_time')
            ->whereNotNull('work_end_time')
            ->chunkById(100, function ($companies) use ($now, $timezone, &$closedCount): void {
                foreach ($companies as $company) {
                    $workingDays = $company->working_days ?? [];
                    if (is_string($workingDays)) {
                        $workingDays = json_decode($workingDays, true) ?: [];
                    }
                    $workingDays = array_map(
                        static fn (string $day): string => strtolower($day),
                        array_filter($workingDays)
                    );

                    $workStartTime = Carbon::parse($company->work_start_time, $timezone)->format('H:i:s');
                    $workEndTime = Carbon::parse($company->work_end_time, $timezone)->format('H:i:s');

                    User::query()
                        ->where('company_id', $company->id)
                        ->whereRaw('LOWER(role) = ?', ['employee'])
                        ->select('id')
                        ->chunkById(100, function ($employees) use (
                            $company,
                            $workingDays,
                            $workStartTime,
                            $workEndTime,
                            $now,
                            $timezone,
                            &$closedCount
                        ): void {
                            foreach ($employees as $employee) {
                                $closedCount += DB::transaction(function () use (
                                    $employee,
                                    $company,
                                    $workingDays,
                                    $workStartTime,
                                    $workEndTime,
                                    $now,
                                    $timezone
                                ): int {
                                    $lockedUser = User::query()
                                        ->whereKey($employee->id)
                                        ->lockForUpdate()
                                        ->first();

                                    if (! $lockedUser) {
                                        return 0;
                                    }

                                    $profile = PayrollProfile::query()
                                        ->where('user_id', $lockedUser->id)
                                        ->lockForUpdate()
                                        ->first();

                                    // Only an explicit overtime opt-in allows the employee
                                    // to remain clocked in after the scheduled shift end.
                                    if ($profile?->overtime_eligible === true) {
                                        return 0;
                                    }

                                    $latestLog = AttendanceLog::query()
                                        ->where('company_id', $company->id)
                                        ->where('user_id', $lockedUser->id)
                                        ->orderByDesc('timestamp')
                                        ->orderByDesc('id')
                                        ->lockForUpdate()
                                        ->first();

                                    if (
                                        ! $latestLog
                                        || $latestLog->type !== 'clock_in'
                                        || $latestLog->status !== 'verified'
                                        || $latestLog->is_suspicious
                                    ) {
                                        return 0;
                                    }

                                    $clockInAt = $latestLog->timestamp->copy()->timezone($timezone);
                                    $shiftDay = strtolower($clockInAt->format('l'));

                                    if ($workingDays !== [] && ! in_array($shiftDay, $workingDays, true)) {
                                        return 0;
                                    }

                                    $shiftStart = Carbon::createFromFormat(
                                        'Y-m-d H:i:s',
                                        $clockInAt->toDateString().' '.$workStartTime,
                                        $timezone
                                    );
                                    $shiftEnd = Carbon::createFromFormat(
                                        'Y-m-d H:i:s',
                                        $clockInAt->toDateString().' '.$workEndTime,
                                        $timezone
                                    );

                                    // Support overnight shifts as well as ordinary day shifts.
                                    if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                                        if ($clockInAt->format('H:i:s') < $workEndTime) {
                                            $shiftStart->subDay();
                                        } else {
                                            $shiftEnd->addDay();
                                        }
                                    }

                                    if (
                                        $clockInAt->greaterThanOrEqualTo($shiftEnd)
                                        || $now->lessThan($shiftEnd)
                                    ) {
                                        return 0;
                                    }

                                    AttendanceLog::query()->create([
                                        'user_id' => $lockedUser->id,
                                        'company_id' => $company->id,
                                        'timestamp' => $shiftEnd->copy()->setTimezone(config('app.timezone', 'UTC')),
                                        'type' => 'clock_out',
                                        'latitude' => $latestLog->latitude,
                                        'longitude' => $latestLog->longitude,
                                        'distance_meters' => $latestLog->distance_meters,
                                        'photo_path' => null,
                                        'source' => 'system',
                                        'status' => 'verified',
                                        'is_suspicious' => false,
                                        'suspicion_reason' => null,
                                    ]);

                                    return 1;
                                });
                            }
                        });
                }
            });

        $this->info("Automatically clocked out {$closedCount} employee(s).");

        return self::SUCCESS;
    }
}
