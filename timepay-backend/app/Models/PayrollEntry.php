<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'payroll_period_id', 'user_id', 'worked_minutes', 'payable_days', 'gross_pay',
        'employee_deductions', 'employer_contributions', 'net_pay', 'employer_cost', 'amount_paid',
        'calculation_snapshot', 'review_flags',
    ];

    protected function casts(): array
    {
        return [
            'worked_minutes' => 'integer', 'payable_days' => 'integer', 'gross_pay' => 'decimal:2',
            'employee_deductions' => 'decimal:2', 'employer_contributions' => 'decimal:2', 'net_pay' => 'decimal:2',
            'employer_cost' => 'decimal:2', 'amount_paid' => 'decimal:2', 'calculation_snapshot' => 'array',
            'review_flags' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollEntryLine::class);
    }

    public function attendanceLogs(): BelongsToMany
    {
        return $this->belongsToMany(AttendanceLog::class, 'payroll_entry_attendance_logs');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }
}
