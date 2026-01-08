<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\CorrectionBreak;

class AttendanceCorrectionsTableSeeder extends Seeder
{
    public function run()
    {
        $targetUserIds  = [2, 3, 4];
        $approverUserId = 1;

        $dateFrom = '2025-12-01';
        $dateTo   = '2025-12-31';

        DB::transaction(function () use ($targetUserIds, $approverUserId, $dateFrom, $dateTo) {

            foreach ($targetUserIds as $userId) {

                $attendances = Attendance::query()
                    ->where('user_id', $userId)
                    ->whereBetween('work_date', [$dateFrom, $dateTo])
                    ->whereNotNull('clock_in_at')
                    ->whereNotNull('clock_out_at')
                    ->with(['breaks' => fn ($q) => $q->orderBy('break_start_at')])
                    ->orderBy('work_date')
                    ->limit(3)
                    ->get();

                $attendanceIds = $attendances->pluck('id');

                $existingIds = AttendanceCorrection::whereIn('attendance_id', $attendanceIds)->pluck('id');
                if ($existingIds->isNotEmpty()) {
                    CorrectionBreak::whereIn('attendance_correction_id', $existingIds)->delete();
                    AttendanceCorrection::whereIn('id', $existingIds)->delete();
                }

                foreach ($attendances as $index => $attendance) {
                    $workDate = $attendance->work_date->toDateString();

                    $break1 = $attendance->breaks->get(0);
                    $break2 = $attendance->breaks->get(1);

                    $isPending = ($index === 0);

                    if ($isPending) {
                        $correction = AttendanceCorrection::create([
                            'attendance_id'          => $attendance->id,
                            'requested_clock_in_at'  => Carbon::parse("$workDate 09:00:00"),
                            'requested_clock_out_at' => Carbon::parse("$workDate 18:30:00"),
                            'reason'                 => '遅延のため',
                            'status'                 => AttendanceCorrection::STATUS_PENDING,
                            'type'                   => AttendanceCorrection::TYPE_EMPLOYEE_REQUEST,
                            'approved_by_user_id'    => null,
                            'approved_at'            => null,
                        ]);
                    } else {
                        $correction = AttendanceCorrection::create([
                            'attendance_id'          => $attendance->id,
                            'requested_clock_in_at'  => $attendance->clock_in_at,
                            'requested_clock_out_at' => $attendance->clock_out_at,
                            'reason'                 => '遅延のため',
                            'status'                 => AttendanceCorrection::STATUS_APPROVED,
                            'type'                   => AttendanceCorrection::TYPE_EMPLOYEE_REQUEST,
                            'approved_by_user_id'    => $approverUserId,
                            'approved_at'            => now(),
                        ]);
                    }

                    CorrectionBreak::insert([
                        [
                            'attendance_correction_id' => $correction->id,
                            'break_start_at'           => $break1?->break_start_at ?? Carbon::parse("$workDate 12:15:00"),
                            'break_end_at'             => $break1?->break_end_at   ?? Carbon::parse("$workDate 13:00:00"),
                            'created_at'               => now(),
                            'updated_at'               => now(),
                        ],
                        [
                            'attendance_correction_id' => $correction->id,
                            'break_start_at'           => $break2?->break_start_at ?? Carbon::parse("$workDate 15:15:00"),
                            'break_end_at'             => $break2?->break_end_at   ?? Carbon::parse("$workDate 15:40:00"),
                            'created_at'               => now(),
                            'updated_at'               => now(),
                        ],
                    ]);
                }

                $adminDirectAttendance = $attendances->last();

                if ($adminDirectAttendance) {
                    $adminWorkDate = $adminDirectAttendance->work_date->toDateString();

                    $adminBreak1 = $adminDirectAttendance->breaks->get(0);
                    $adminBreak2 = $adminDirectAttendance->breaks->get(1);

                    $adminCorrection = AttendanceCorrection::create([
                        'attendance_id'          => $adminDirectAttendance->id,
                        'requested_clock_in_at'  => Carbon::parse("$adminWorkDate 09:10:00"),
                        'requested_clock_out_at' => Carbon::parse("$adminWorkDate 18:20:00"),
                        'reason'                 => '管理者による勤怠修正',
                        'status'                 => AttendanceCorrection::STATUS_APPROVED,
                        'type'                   => AttendanceCorrection::TYPE_ADMIN_DIRECT,
                        'approved_by_user_id'    => $approverUserId,
                        'approved_at'            => now(),
                    ]);

                    CorrectionBreak::insert([
                        [
                            'attendance_correction_id' => $adminCorrection->id,
                            'break_start_at'           => $adminBreak1?->break_start_at ?? Carbon::parse("$adminWorkDate 12:15:00"),
                            'break_end_at'             => Carbon::parse("$adminWorkDate 13:05:00"),
                            'created_at'               => now(),
                            'updated_at'               => now(),
                        ],
                        [
                            'attendance_correction_id' => $adminCorrection->id,
                            'break_start_at'           => $adminBreak2?->break_start_at ?? Carbon::parse("$adminWorkDate 15:15:00"),
                            'break_end_at'             => Carbon::parse("$adminWorkDate 15:35:00"),
                            'created_at'               => now(),
                            'updated_at'               => now(),
                        ],
                    ]);
                }
            }
        });
    }
}