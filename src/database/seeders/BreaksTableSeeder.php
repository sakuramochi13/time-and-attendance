<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\BreakTime;

class BreaksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        DB::transaction(function () {
            $attendances = Attendance::query()
                ->whereBetween('work_date', ['2025-12-01', '2025-12-31'])
                ->whereBetween('user_id', [1, 7])
                ->whereNotNull('clock_in_at')
                ->whereNotNull('clock_out_at')
                ->get(['id', 'work_date']);

            foreach ($attendances as $attendance) {
                $workDate = $attendance->work_date->toDateString();

                if (BreakTime::where('attendance_id', $attendance->id)->count() >= 2) {
                    continue;
                }

                BreakTime::insert([
                    [
                        'attendance_id'  => $attendance->id,
                        'break_start_at' => Carbon::parse($workDate . ' 12:15:00'),
                        'break_end_at'   => Carbon::parse($workDate . ' 13:00:00'),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ],
                    [
                        'attendance_id'  => $attendance->id,
                        'break_start_at' => Carbon::parse($workDate . ' 15:15:00'),
                        'break_end_at'   => Carbon::parse($workDate . ' 15:30:00'),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ],
                ]);
            }
        });
    }
}
