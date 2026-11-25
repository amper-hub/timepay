<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'user_id', 'salary_type', 'pay_frequency', 'withholding_tax_enabled',
        'standard_hours_per_day', 'standard_work_days_per_month',
    ];

    protected function casts(): array
    {
        return [
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

}
