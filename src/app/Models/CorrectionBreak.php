<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionBreak extends Model
{
    use HasFactory;

    protected $casts = [
        'break_start_at' => 'datetime',
        'break_end_at'   => 'datetime',
    ];


    protected $fillable = [
        'attendance_correction_id',
        'break_start_at',
        'break_end_at',
    ];

    public function attendanceCorrection(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class, 'attendance_correction_id', 'id');
    }
}
