<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\CorrectionBreak;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AttendanceCorrectionController extends Controller
{
    public function show(Request $request, $id)
    {
        $userId = auth()->id();

        $attendance = Attendance::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->with([
                'user',
                'breaks' => fn ($breakQuery) => $breakQuery->orderBy('break_start_at'),
            ])
            ->firstOrFail();

        $correctionId = $request->query('correction_id');
        $correction = null;
        $correctionBreaks = collect();

        if ($correctionId) {
            $correction = AttendanceCorrection::query()
                ->where('id', $correctionId)
                ->where('attendance_id', $attendance->id)
                ->with(['correctionBreaks' => fn ($correctionBreakQuery) => $correctionBreakQuery->orderBy('break_start_at'),])
                ->firstOrFail();

            $correctionBreaks = $correction->correctionBreaks;
        }

        $isReadonly = (bool) $correction;

        $hasPendingCorrections = AttendanceCorrection::query()
            ->where('attendance_id', $attendance->id)
            ->where('status', AttendanceCorrection::STATUS_PENDING)
            ->exists();

        $canRequestNew = !($hasPendingCorrections && empty($correctionId));

        $oldBreaks  = old('breaks');
        $baseBreaks = $attendance->breaks ?? collect();

        $rowsCount = is_array($oldBreaks)
            ? count($oldBreaks)
            : max($baseBreaks->count() + 1, 1);

        $breakRows = collect(range(0, $rowsCount - 1))->map(function ($rowIndex) use ($oldBreaks, $baseBreaks) {
            $oldStart = is_array($oldBreaks) ? ($oldBreaks[$rowIndex]['start'] ?? null) : null;
            $oldEnd   = is_array($oldBreaks) ? ($oldBreaks[$rowIndex]['end'] ?? null) : null;

            $break = $baseBreaks->get($rowIndex);

            return [
                'index' => $rowIndex,
                'label' => $rowIndex === 0 ? '休憩' : '休憩' . ($rowIndex + 1),
                'start' => $oldStart ?? optional(optional($break)->break_start_at)->format('H:i'),
                'end'   => $oldEnd   ?? optional(optional($break)->break_end_at)->format('H:i'),
            ];
        });


        return view('attendance.detail.show', compact(
            'attendance',
            'correction',
            'isReadonly',
            'correctionBreaks',
            'hasPendingCorrections',
            'canRequestNew',
            'breakRows'
        ));
    }

    public function store(AttendanceCorrectionRequest $request, $id)
    {
        $attendance = Attendance::query()
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $toDateTime = function (?string $hm) use ($attendance) {
            if (!$hm) return null;
            return Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $hm);
        };

        $requestedIn  = $toDateTime($request->input('clock_in_at'));
        $requestedOut = $toDateTime($request->input('clock_out_at'));

        $breaksInput = $request->input('breaks', []);

        $correction = DB::transaction(function () use ($request, $attendance, $requestedIn, $requestedOut, $breaksInput, $toDateTime) {

            $correction = AttendanceCorrection::create([
                'attendance_id'          => $attendance->id,
                'requested_clock_in_at'  => $requestedIn,
                'requested_clock_out_at' => $requestedOut,
                'reason'                 => $request->input('reason'),
                'status'                 => AttendanceCorrection::STATUS_PENDING,
                'type'                   => AttendanceCorrection::TYPE_EMPLOYEE_REQUEST,
            ]);

            foreach ($breaksInput as $breakInput) {
                $startHm = $breakInput['start'] ?? null;
                $endHm   = $breakInput['end'] ?? null;

                if (!$startHm && !$endHm) continue;

                CorrectionBreak::create([
                    'attendance_correction_id' => $correction->id,
                    'break_start_at'           => $toDateTime($startHm),
                    'break_end_at'             => $toDateTime($endHm),
                ]);
            }

            return $correction;
        });

        return redirect(route('attendance.detail.show', $attendance->id) . '?correction_id=' . $correction->id)
            ->with('success', '修正依頼を送信しました（承認待ち）');
    }
}
