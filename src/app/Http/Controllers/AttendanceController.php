<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\AttendanceCorrection;


class AttendanceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        return view('attendance.index', compact('attendance'));
    }

    public function clockIn(Request $request)
    {
        $user = $request->user();
        $now  = Carbon::now();

        $attendance = Attendance::firstOrCreate(
            [
                'user_id'   => $user->id,
                'work_date' => $now->toDateString(),
            ],
            [
                'work_status' => 0,
            ]
        );

        if ($attendance->clock_in_at) {
            return back();
        }

        $attendance->clock_in_at = $now;
        $attendance->work_status = 1;
        $attendance->save();

        return redirect()
            ->route('attendance.index');
    }

    public function clockOut(Request $request)
    {
        $user = $request->user();
        $now  = Carbon::now();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->first();

        if (!$attendance || !$attendance->clock_in_at) {
            return back();
        }

        if ($attendance->clock_out_at) {
            return back();
        }

        $attendance->clock_out_at = $now;
        $attendance->work_status = 3;
        $attendance->save();

        return redirect()->route('attendance.index');
    }

    public function breakStart(Request $request)
    {
        $user = $request->user();
        $now  = Carbon::now();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->first();

        if (!$attendance || !$attendance->clock_in_at) {
            return back();
        }

        if ($attendance->work_status === 2) {
            return back();
        }

        if ($attendance->work_status === 3) {
            return back();
        }

        BreakTime::create([
            'attendance_id'  => $attendance->id,
            'break_start_at' => $now,
        ]);

        $attendance->work_status = 2;
        $attendance->save();

        return redirect()->route('attendance.index');
    }

    public function breakEnd(Request $request)
    {
        $user = $request->user();
        $now  = Carbon::now();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->first();

        if (!$attendance || !$attendance->clock_in_at) {
            return back();
        }

        if ($attendance->work_status !== 2) {
            return back();
        }

        $break = BreakTime::where('attendance_id', $attendance->id)
            ->whereNull('break_end_at')
            ->latest('break_start_at')
            ->first();

        if (!$break) {
            return back();
        }

        $break->break_end_at = $now;
        $break->save();

        $attendance->work_status = 1;
        $attendance->save();

        return redirect()->route('attendance.index');
    }

    public function list(Request $request)
    {
        $user = Auth::user();

        $monthParam = $request->query('month');

        if ($monthParam) {
            try {
                $currentMonth = Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth();
            } catch (\Exception $dateParseException) {
                $currentMonth = Carbon::now()->startOfMonth();
            }
        } else {
            $currentMonth = Carbon::now()->startOfMonth();
        }

        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth   = $currentMonth->copy()->endOfMonth();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('work_date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->with('breaks')
            ->orderBy('work_date')
            ->get();

        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        $hasPendingCorrections = AttendanceCorrection::query()
        ->where('status', 0)
        ->whereHas('attendance', function ($attendanceQuery) use ($user) {
            $attendanceQuery->where('user_id', $user->id);
        })
        ->exists();

        return view('attendance.list', [
            'attendances'  => $attendances,
            'currentMonth' => $currentMonth,
            'prevMonth'    => $prevMonth,
            'nextMonth'    => $nextMonth,
            'hasPendingCorrections' => $hasPendingCorrections,
        ]);
    }
}
