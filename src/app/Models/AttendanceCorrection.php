<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceCorrection extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 0; // 承認待ち
    public const STATUS_APPROVED = 1; // 承認済
    public const STATUS_REJECTED = 2; // 却下

    public const TYPE_EMPLOYEE_REQUEST = 1; // 従業員申請
    public const TYPE_ADMIN_DIRECT     = 2; // 管理者直接修正

    protected $casts = [
        'requested_clock_in_at'  => 'datetime',
        'requested_clock_out_at' => 'datetime',
        'approved_at'            => 'datetime',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING  => '承認待ち',
            self::STATUS_APPROVED => '承認済み',
            self::STATUS_REJECTED => '却下',
            default => '—',
        };
    }

    protected $fillable = [
        'attendance_id',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'reason',
        'status',
        'type',
        'approved_by_user_id',
        'approved_at',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function correctionBreaks(): HasMany
    {
        return $this->hasMany(CorrectionBreak::class, 'attendance_correction_id', 'id');
    }
    public function getUserNameAttribute(): string
    {
        return $this->attendance?->user?->name ?? '';
    }

    public function getWorkDateYmdAttribute(): string
    {
        $date = $this->attendance?->work_date;
        return $date ? $date->format('Y/m/d') : '—';
    }

    public function getReasonTextAttribute(): string
    {
        return $this->reason ?: '—';
    }

    public function getRequestedAtYmdAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('Y/m/d') : '—';
    }
}
