<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;

class AttendancesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $userIds = range(1, 7);

        $start = Carbon::parse('2025-12-01');
        $end   = Carbon::parse('2025-12-31');

        DB::transaction(function () use ($userIds, $start, $end) {
            foreach ($userIds as $userId) {
                $date = $start->copy();

                while ($date->lte($end)) {
                    $workDate = $date->toDateString();
                    $isWeekday = $date->isWeekday();

                    Attendance::updateOrCreate(
                        [
                            'user_id'   => $userId,
                            'work_date' => $workDate,
                        ],
                        [
                            'clock_in_at'  => $isWeekday ? Carbon::parse($workDate . ' 09:00:00') : null,
                            'clock_out_at' => $isWeekday ? Carbon::parse($workDate . ' 18:00:00') : null,
                            'work_status'  => $isWeekday ? 3 : 0, // 平日=退勤済 / 土日=勤務外
                        ]
                    );

                    $date->addDay();
                }
            }
        });
    }
}
