<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntryLine extends Model
{
    protected $fillable = ['payroll_entry_id', 'category', 'code', 'label', 'amount', 'quantity', 'rate', 'taxable', 'details'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'quantity' => 'decimal:4', 'rate' => 'decimal:4', 'taxable' => 'boolean', 'details' => 'array'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }
}
