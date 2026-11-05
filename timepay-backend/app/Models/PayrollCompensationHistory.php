<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollCompensationHistory extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'user_id', 'basis', 'rate', 'effective_from', 'effective_to', 'changed_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
