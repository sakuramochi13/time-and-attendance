<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\BreakTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionController extends Controller
{
    public function show(AttendanceCorrection $attendance_correction)
    {
        $attendance_correction->load([
            'attendance',
            'correctionBreaks',
        ]);

        return view('stamp_correction_request.approve.show', [
            'correction' => $attendance_correction,
            'attendance' => $attendance_correction->attendance,
            'correctionBreaks' => $attendance_correction->correctionBreaks,
        ]);
    }

    public function update(Request $request, AttendanceCorrection $attendance_correction)
    {
        if ((int)$attendance_correction->status === 1) {
            return back()->with('error', 'この申請はすでに承認済みです。');
        }

        DB::transaction(function () use ($attendance_correction) {
            $attendance_correction->load(['attendance', 'correctionBreaks']);

            $attendance = $attendance_correction->attendance;

            $attendance->clock_in_at  = $attendance_correction->requested_clock_in_at;
            $attendance->clock_out_at = $attendance_correction->requested_clock_out_at;
            $attendance->save();

            BreakTime::query()->where('attendance_id', $attendance->id)->delete();

            foreach ($attendance_correction->correctionBreaks as $cb) {
                BreakTime::create([
                    'attendance_id'   => $attendance->id,
                    'break_start_at'  => $cb->break_start_at,
                    'break_end_at'    => $cb->break_end_at,
                ]);
            }

            $attendance_correction->status = 1;
            $attendance_correction->approved_by_user_id = Auth::id();
            $attendance_correction->approved_at = now();
            $attendance_correction->save();
        });

        return redirect()
            ->route('stamp_correction_request.approve.show', $attendance_correction)
            ->with('success', '申請を承認し、勤怠に反映しました。');
    }
}
