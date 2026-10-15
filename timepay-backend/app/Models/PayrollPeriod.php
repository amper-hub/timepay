<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'period_start', 'period_end', 'pay_date', 'frequency', 'status', 'gross_total',
        'deduction_total', 'employer_contribution_total', 'net_total', 'paid_total', 'created_by',
        'calculated_by', 'calculated_at', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'pay_date' => 'date', 'calculated_at' => 'datetime',
            'approved_at' => 'datetime', 'gross_total' => 'decimal:2', 'deduction_total' => 'decimal:2',
            'employer_contribution_total' => 'decimal:2', 'net_total' => 'decimal:2', 'paid_total' => 'decimal:2',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }
}
