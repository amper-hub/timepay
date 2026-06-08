<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory, BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'timestamp',
        'type',
        'latitude',
        'longitude',
        'distance_meters',
        'photo_path',
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
            'timestamp' => 'datetime',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'distance_meters' => 'decimal:2',
            'type' => 'string',
            'status' => 'string',
        ];
    }

    /**
     * Get the company that owns the attendance log.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user associated with the attendance log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
