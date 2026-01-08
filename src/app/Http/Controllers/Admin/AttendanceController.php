<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\AttendanceUpdateRequest;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\BreakTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $currentDate = $request->filled('date')
            ? CarbonImmutable::parse($request->query('date'))->startOfDay()
            : CarbonImmutable::today();

        $prevDate = $currentDate->subDay();
        $nextDate = $currentDate->addDay();

        $staff = User::query()
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->where('name', 'employee');
            })
            ->with([
                'attendances' => function ($attendanceQuery) use ($currentDate) {
                    $attendanceQuery
                        ->whereDate('work_date', $currentDate->toDateString())
                        ->with('breaks');
                }
            ])
            ->orderBy('name')
            ->get();

        return view('admin.attendance.list', compact(
            'staff',
            'currentDate',
            'prevDate',
            'nextDate',
        ));
    }

    public function showByUserDate(User $user, string $date)
    {
        $workDate = CarbonImmutable::parse($date)->toDateString();

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->with('breaks')
            ->first();

        if (!$attendance) {
            $attendance = new Attendance([
                'user_id'   => $user->id,
                'work_date' => $workDate,
            ]);
            $attendance->setRelation('user', $user);
            $attendance->setRelation('breaks', collect());
        }

        $isLocked = $attendance->exists
            ? AttendanceCorrection::query()
                ->where('attendance_id', $attendance->id)
                ->where('status', AttendanceCorrection::STATUS_PENDING)
                ->exists()
            : false;

        $breakRows = $this->buildBreakFormRows($attendance, request());

        return view('admin.attendance.show', compact('attendance', 'isLocked', 'breakRows'));
    }


    public function show(Attendance $attendance)
    {
        $attendance->load(['user', 'breaks']);

        $isLocked = AttendanceCorrection::query()
            ->where('attendance_id', $attendance->id)
            ->where('status', AttendanceCorrection::STATUS_PENDING)
            ->exists();

        $breakRows = $this->buildBreakFormRows($attendance, request());

        return view('admin.attendance.show', compact('attendance', 'isLocked', 'breakRows'));
    }

    public function update(AttendanceUpdateRequest $request, Attendance $attendance)
    {
        if (
            AttendanceCorrection::where('attendance_id', $attendance->id)
                ->where('status', AttendanceCorrection::STATUS_PENDING)
                ->exists()
        ) {
            abort(403, '承認待ちのため修正はできません。');
        }

        $attendance->load('breaks');

        $workDate = CarbonImmutable::parse($attendance->work_date)->toDateString();

        $clockIn  = $this->toWorkDatetime($workDate, $request->clock_in_at);
        $clockOut = $this->toWorkDatetime($workDate, $request->clock_out_at);

        $breakInputs = collect($request->input('breaks', []))
            ->map(function ($row) use ($workDate) {
                return [
                    'start' => $this->toWorkDatetimeOrNull($workDate, $row['break_start_at'] ?? null),
                    'end'   => $this->toWorkDatetimeOrNull($workDate, $row['break_end_at'] ?? null),
                ];
            })
            ->filter(fn ($breakInput) => $breakInput['start'] && $breakInput['end'])
            ->values();

        DB::transaction(function () use (
            $attendance,
            $request,
            $clockIn,
            $clockOut,
            $breakInputs
        ) {

            $correction = AttendanceCorrection::create([
                'attendance_id'          => $attendance->id,
                'requested_clock_in_at'  => $clockIn,
                'requested_clock_out_at' => $clockOut,
                'reason'                 => $request->reason,
                'status'                 => AttendanceCorrection::STATUS_APPROVED,
                'type'                   => AttendanceCorrection::TYPE_ADMIN_DIRECT,
                'approved_by_user_id'    => auth()->id(),
                'approved_at'            => now(),
            ]);

            $breakInputs = collect($breakInputs);

            $workStatus = Attendance::resolveWorkStatus(
                $clockIn,
                $clockOut,
                $breakInputs
            );

            $attendance->update([
                'clock_in_at'  => $clockIn,
                'clock_out_at' => $clockOut,
                'work_status'  => $workStatus,
            ]);

            $attendance->breaks()->delete();

            foreach ($breakInputs as $breakInput) {
                BreakTime::create([
                    'attendance_id'  => $attendance->id,
                    'break_start_at' => $breakInput['start'],
                    'break_end_at'   => $breakInput['end'],
                ]);
            }

            if ($breakInputs->isNotEmpty()) {
                $insert = $breakInputs->map(fn ($breakInput) => [
                    'attendance_correction_id' => $correction->id,
                    'break_start_at'           => $breakInput['start'],
                    'break_end_at'             => $breakInput['end'],
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ])->all();

                DB::table('correction_breaks')->insert($insert);
            }
        });

        return redirect()
            ->route('admin.attendance.show', $attendance)
            ->with('success', '勤務時間を更新しました。');
    }

    private function toWorkDatetime(string $workDate, string $hhmm): CarbonImmutable
    {
        return CarbonImmutable::parse($workDate . ' ' . $hhmm . ':00');
    }

    private function toWorkDatetimeOrNull(string $workDate, ?string $hhmm): ?CarbonImmutable
    {
        if (!$hhmm) return null;
        return CarbonImmutable::parse($workDate . ' ' . $hhmm . ':00');
    }

    public function staffShow(Request $request, User $user)
    {
        $currentMonth = $request->filled('month')
            ? CarbonImmutable::parse($request->query('month') . '-01')->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();

        $prevMonth = $currentMonth->subMonth();
        $nextMonth = $currentMonth->addMonth();

        $attendances = $user->attendances()
            ->whereBetween('work_date', [
                $currentMonth->toDateString(),
                $currentMonth->endOfMonth()->toDateString(),
            ])
            ->with('breaks')
            ->orderBy('work_date')
            ->get();

        return view('admin.attendance.staff.show', compact(
            'user',
            'attendances',
            'currentMonth',
            'prevMonth',
            'nextMonth',
        ));
    }

    public function staffExportCsv(Request $request, User $user): StreamedResponse
{
    $currentMonth = $request->filled('month')
        ? CarbonImmutable::parse($request->query('month') . '-01')->startOfMonth()
        : CarbonImmutable::today()->startOfMonth();

    $attendances = $user->attendances()
        ->whereBetween('work_date', [
            $currentMonth->toDateString(),
            $currentMonth->endOfMonth()->toDateString(),
        ])
        ->with('breaks')
        ->orderBy('work_date')
        ->get();

    $filename = sprintf(
        '%s年%s月　%sさんの勤怠.csv',
        $currentMonth->year,
        $currentMonth->month,
        $user->name
    );

    return response()->streamDownload(function () use ($attendances, $user, $currentMonth) {
        $out = fopen('php://output', 'w');

        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            sprintf(
                '%s年%s月　%sさんの勤怠',
                $currentMonth->year,
                $currentMonth->month,
                $user->name
            )
        ]);

        fputcsv($out, ['日付', '出勤', '退勤', '休憩', '合計']);

        $totalBreakMinutes = 0;
        $totalWorkMinutes  = 0;

        foreach ($attendances as $attendance) {
            $breakMinutes = (int) $attendance->break_minutes;
            $workMinutes  = (int) $attendance->working_minutes;

            $totalBreakMinutes += $breakMinutes;
            $totalWorkMinutes  += $workMinutes;

            fputcsv($out, [
                optional($attendance->work_date)->isoFormat('MM/DD(ddd)') ?? '',
                optional($attendance->clock_in_at)->format('H:i') ?? '',
                optional($attendance->clock_out_at)->format('H:i') ?? '',
                $this->minutesToHmText($breakMinutes, true),
                $this->minutesToHmText($workMinutes, true),
            ]);
        }

        fputcsv($out, []);

        fputcsv($out, [
            '【当月合計】',
            '',
            '',
            $this->minutesToHmText($totalBreakMinutes, true),
            $this->minutesToHmText($totalWorkMinutes, true),
        ]);

        fclose($out);
    }, $filename, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
}

    private function minutesToHmText(int $minutes, bool $blankWhenZero = true): string
    {
        if ($minutes <= 0) {
            return $blankWhenZero ? '' : '0:00';
        }

        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;

        return $hours . ':' . sprintf('%02d', $mins);
    }

    public function store(AttendanceUpdateRequest $request)
    {
    $userId   = (int) $request->input('user_id');
    $workDate = CarbonImmutable::parse($request->input('work_date'))->toDateString();

    $clockIn  = $this->toWorkDatetime($workDate, $request->clock_in_at);
    $clockOut = $this->toWorkDatetime($workDate, $request->clock_out_at);

    $breakInputs = collect($request->input('breaks', []))
        ->map(function ($row) use ($workDate) {
            return [
                'start' => $this->toWorkDatetimeOrNull($workDate, $row['break_start_at'] ?? null),
                'end'   => $this->toWorkDatetimeOrNull($workDate, $row['break_end_at'] ?? null),
            ];
        })
        ->filter(fn ($breakInput) => $breakInput['start'] && $breakInput['end'])
        ->values();

    $attendance = null;

    DB::transaction(function () use (
        $request,
        $userId,
        $workDate,
        $clockIn,
        $clockOut,
        $breakInputs,
        &$attendance
    ) {

        $attendance = Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $workDate)
            ->lockForUpdate()
            ->first();

        if ($attendance) {
            abort(409, 'この日の勤怠は既に存在します。');
        }


        $workStatus = Attendance::resolveWorkStatus(
            $clockIn,
            $clockOut,
            $breakInputs
        );

        $attendance = Attendance::create([
            'user_id'      => $userId,
            'work_date'    => $workDate,
            'clock_in_at'  => $clockIn,
            'clock_out_at' => $clockOut,
            'work_status'  => $workStatus,
        ]);

        foreach ($breakInputs as $breakInput) {
            BreakTime::create([
                'attendance_id'  => $attendance->id,
                'break_start_at' => $breakInput['start'],
                'break_end_at'   => $breakInput['end'],
            ]);
        }

        $correction = AttendanceCorrection::create([
            'attendance_id'          => $attendance->id,
            'requested_clock_in_at'  => $clockIn,
            'requested_clock_out_at' => $clockOut,
            'reason'                 => $request->reason,
            'status'                 => AttendanceCorrection::STATUS_APPROVED,
            'type'                   => AttendanceCorrection::TYPE_ADMIN_DIRECT,
            'approved_by_user_id'    => auth()->id(),
            'approved_at'            => now(),
        ]);

        if ($breakInputs->isNotEmpty()) {
            $insert = $breakInputs->map(fn ($breakInput) => [
                'attendance_correction_id' => $correction->id,
                'break_start_at'           => $breakInput['start'],
                'break_end_at'             => $breakInput['end'],
                'created_at'               => now(),
                'updated_at'               => now(),
            ])->all();

            DB::table('correction_breaks')->insert($insert);
        }
    });

    return redirect()
        ->route('admin.attendance.show', $attendance)
        ->with('success', '勤怠を作成しました。');
    }

    private function buildBreakFormRows(Attendance $attendance, Request $request): array
    {
        $oldBreaks = $request->old('breaks');

        $baseBreaks = $attendance->breaks ?? collect();

        $slotsCount = is_array($oldBreaks)
            ? count($oldBreaks)
            : ($baseBreaks->count() + 1);

        $rows = [];

        for ($rowIndex = 0; $rowIndex < $slotsCount; $rowIndex++) {
            $break = $baseBreaks->get($rowIndex);

            $rows[] = [
                'index' => $rowIndex,
                'label' => $rowIndex === 0 ? '休憩' : '休憩' . ($rowIndex + 1),

                'id' => is_array($oldBreaks)
                    ? ($oldBreaks[$rowIndex]['id'] ?? '')
                    : (optional($break)->id ?? ''),

                'start' => is_array($oldBreaks)
                    ? ($oldBreaks[$rowIndex]['break_start_at'] ?? '')
                    : (optional(optional($break)->break_start_at)->format('H:i') ?? ''),

                'end' => is_array($oldBreaks)
                    ? ($oldBreaks[$rowIndex]['break_end_at'] ?? '')
                    : (optional(optional($break)->break_end_at)->format('H:i') ?? ''),
            ];
        }

        return $rows;
    }
}