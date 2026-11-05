<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'user_id', 'salary_type', 'pay_frequency', 'payroll_eligible', 'overtime_eligible',
        'rank_and_file', 'sss_enabled', 'philhealth_enabled', 'pagibig_enabled', 'withholding_tax_enabled',
        'standard_hours_per_day', 'standard_work_days_per_month',
    ];

    protected function casts(): array
    {
        return [
            'payroll_eligible' => 'boolean', 'overtime_eligible' => 'boolean', 'rank_and_file' => 'boolean',
            'sss_enabled' => 'boolean', 'philhealth_enabled' => 'boolean', 'pagibig_enabled' => 'boolean',
            'withholding_tax_enabled' => 'boolean', 'standard_hours_per_day' => 'integer',
            'standard_work_days_per_month' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function compensationHistory(): HasMany
    {
        return $this->hasMany(PayrollCompensationHistory::class, 'user_id', 'user_id')->orderBy('effective_from');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollProfileItem::class, 'user_id', 'user_id');
    }
}
