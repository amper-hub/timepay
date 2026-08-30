<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'geofence_radius_meters',
        'geofence_latitude',
        'geofence_longitude',
        'geofence_radius',
        'work_start_time',
        'work_end_time',
        'working_days',
        'pay_metric',
        'currency',
        'monthly_sick_leave_limit',
        'monthly_vacation_leave_limit',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'geofence_radius_meters' => 'integer',
            'geofence_latitude' => 'decimal:8',
            'geofence_longitude' => 'decimal:8',
            'geofence_radius' => 'integer',
            'work_start_time' => 'datetime:H:i',
            'work_end_time' => 'datetime:H:i',
            'working_days' => 'array',
            'pay_metric' => 'string',
            'currency' => 'string',
            'monthly_sick_leave_limit' => 'integer',
            'monthly_vacation_leave_limit' => 'integer',
        ];
    }

    /**
     * Get the display symbol for the company's selected payroll currency.
     */
    public function currencySymbol(): string
    {
        return match ($this->currency) {
            'USD' => '$',
            default => '₱',
        };
    }

    /**
     * Format a monetary value using the company's selected payroll currency.
     */
    public function formatMoney(float|int|string|null $amount): string
    {
        return $this->currencySymbol() . number_format((float) $amount, 2);
    }

    /**
     * Get the users that belong to the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the attendance records for the company.
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Get the leave requests for the company.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the attendance logs for the company.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
