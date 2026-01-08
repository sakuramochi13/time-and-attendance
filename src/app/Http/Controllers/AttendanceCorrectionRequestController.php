<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceCorrection;


class AttendanceCorrectionRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->roles()->where('name', 'admin')->exists();

        $statusParam = $request->query('status', 'pending');

        $statusValue = match ($statusParam) {
            'approved' => AttendanceCorrection::STATUS_APPROVED,
            default    => AttendanceCorrection::STATUS_PENDING,
        };

        $query = AttendanceCorrection::query()
            ->with(['attendance.user'])
            ->where('status', $statusValue);

        if (! $isAdmin) {
            $query->whereHas('attendance', function ($attendanceQuery) use ($user) {
                $attendanceQuery->where('user_id', $user->id);
            });
        }

        $requests = $query->latest()->paginate(40);

        $requests->getCollection()->transform(function ($correction) use ($isAdmin) {
            $attendanceId = $correction->attendance?->id;

            $correction->detail_url = $isAdmin
                ? url('/stamp_correction_request/approve/' . $correction->id)
                : url('/attendance/detail/' . $attendanceId . '?correction_id=' . $correction->id);

            return $correction;
        });

        return view('stamp_correction_request.list', [
            'requests' => $requests,
            'isAdmin'  => $isAdmin,
            'status'   => $statusParam,
        ]);
    }
}
