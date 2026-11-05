<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAttendanceAllocation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'user_id', 'payroll_period_id', 'payroll_entry_id',
        'clock_in_log_id', 'clock_out_log_id', 'original_clock_in_at', 'original_clock_out_at',
        'segment_start_at', 'segment_end_at', 'elapsed_seconds', 'allocation_key',
    ];

    protected function casts(): array
    {
        return [
            'original_clock_in_at' => 'datetime',
            'original_clock_out_at' => 'datetime',
            'segment_start_at' => 'datetime',
            'segment_end_at' => 'datetime',
            'elapsed_seconds' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
