<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceEventResponse extends Model
{
    protected $fillable = ['user_id', 'company_id', 'event_id', 'response'];

    protected function casts(): array
    {
        return ['response' => 'array'];
    }
}
