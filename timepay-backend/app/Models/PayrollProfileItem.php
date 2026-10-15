<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PayrollProfileItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'user_id', 'category', 'code', 'label', 'amount', 'taxable', 'authorization_reference', 'effective_from', 'effective_to', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'taxable' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date'];
    }
}
