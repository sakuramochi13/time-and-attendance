<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BreakTime;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'work_status',
    ];

    protected $casts = [
        'work_date'    => 'date',
        'clock_in_at'  => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakTime::class, 'attendance_id');
    }

    public function getBreakMinutesAttribute(): int
    {
        return $this->breaks->reduce(function ($carry, $break) {
            if (!$break->break_start_at || !$break->break_end_at) {
                return $carry;
            }

            $start = Carbon::parse($break->break_start_at);
            $end   = Carbon::parse($break->break_end_at);

            return $carry + $start->diffInMinutes($end);
        }, 0);
    }

    public function getWorkingMinutesAttribute(): int
    {
        if (!$this->clock_in_at || !$this->clock_out_at) {
            return 0;
        }

        $total = $this->clock_out_at->diffInMinutes($this->clock_in_at);

        return max($total - $this->break_minutes, 0);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function getBreakDurationAttribute(): string
    {
        $minutes = (int) $this->break_minutes;
        if ($minutes <= 0) return '';

        $hours = intdiv($minutes, 60);
        $minutes = $minutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    public function getWorkingDurationAttribute(): string
    {
        $minutes = (int) $this->working_minutes;
        if ($minutes <= 0) return '';

        $hours = intdiv($minutes, 60);
        $minutes = $minutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    public const WORK_STATUS_OFF      = 0; // 勤務外
    public const WORK_STATUS_WORKING  = 1; // 勤務中
    public const WORK_STATUS_BREAK    = 2; // 休憩中
    public const WORK_STATUS_FINISHED = 3; // 退勤済

    public static function resolveWorkStatus($clockIn, $clockOut, $breakInputs): int
    {
        if ($clockOut) {
            return self::WORK_STATUS_FINISHED;
        }

        $isOnBreakNow = $breakInputs->contains(function ($b) {
            return !empty($b['start']) && empty($b['end']);
        });

        if ($isOnBreakNow) {
            return self::WORK_STATUS_BREAK;
        }

        if ($clockIn) {
            return self::WORK_STATUS_WORKING;
        }

        return self::WORK_STATUS_OFF;
    }

    public function getWorkStatusLabelAttribute(): string
    {
        return match ($this->work_status) {
            self::WORK_STATUS_WORKING  => '勤務中',
            self::WORK_STATUS_BREAK    => '休憩中',
            self::WORK_STATUS_FINISHED => '退勤済',
            default                    => '勤務外',
        };
    }

    public function isWorkOff(): bool
    {
        return $this->work_status === self::WORK_STATUS_OFF;
    }

    public function isWorking(): bool
    {
        return $this->work_status === self::WORK_STATUS_WORKING;
    }

    public function isOnBreak(): bool
        {
        return $this->work_status === self::WORK_STATUS_BREAK;
    }

    public function isFinished(): bool
    {
        return $this->work_status === self::WORK_STATUS_FINISHED;
    }
}
