<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory, BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'date',
        'time_in',
        'time_out',
        'latitude_in',
        'longitude_in',
        'latitude_out',
        'longitude_out',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'time_in' => 'time',
            'time_out' => 'time',
            'latitude_in' => 'decimal:8',
            'longitude_in' => 'decimal:8',
            'latitude_out' => 'decimal:8',
            'longitude_out' => 'decimal:8',
            'status' => 'string',
        ];
    }

    /**
     * Get the company that owns the attendance record.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user associated with the attendance record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
