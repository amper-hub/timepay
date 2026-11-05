<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollStatutoryRule extends Model
{
    protected $fillable = ['code', 'name', 'effective_from', 'effective_to', 'configuration', 'source_url', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'configuration' => 'array'];
    }
}
