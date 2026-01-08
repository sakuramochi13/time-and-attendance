<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    private function freezeNow(int $year, int $month, int $day, int $hour = 10, int $minute = 0): Carbon
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $fixedNow = Carbon::create($year, $month, $day, $hour, $minute, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);
        return $fixedNow;
    }

    private function createAttendance(
        int $userId,
        string $workDate,
        string $clockIn,
        string $clockOut,
        int $workStatus = 3
    ): int {
        return DB::table('attendances')->insertGetId([
            'user_id' => $userId,
            'work_date' => $workDate,
            'clock_in_at' => "{$workDate} {$clockIn}:00",
            'clock_out_at' => "{$workDate} {$clockOut}:00",
            'work_status' => $workStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBreak(int $attendanceId, string $workDate, string $start, string $end): void
    {
        DB::table('breaks')->insert([
            'attendance_id' => $attendanceId,
            'break_start_at' => "{$workDate} {$start}:00",
            'break_end_at' => "{$workDate} {$end}:00",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function 勤怠詳細画面の名前がログインユーザーの氏名になっている()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create([
            'name' => '神谷 真理子',
        ]);

        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $response = $this->get("/attendance/detail/{$attendanceId}");
        $response->assertStatus(200);

        $response->assertSee('神谷 真理子');

        Carbon::setTestNow();
    }

    /** @test */
    public function 勤怠詳細画面の日付が選択した日付になっている()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $response = $this->get("/attendance/detail/{$attendanceId}");
        $response->assertStatus(200);

        $response->assertSee('2026年');
        $response->assertSee('1月');
        $response->assertSee('6日');

        Carbon::setTestNow();
    }

    /** @test */
    public function 出勤退勤にて記されている時間がログインユーザーの打刻と一致している()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:15', '18:45');

        $this->actingAs($user);

        $response = $this->get("/attendance/detail/{$attendanceId}");
        $response->assertStatus(200);

        $response->assertSee('09:15');
        $response->assertSee('18:45');

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩にて記されている時間がログインユーザーの打刻と一致している()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->createBreak($attendanceId, '2026-01-06', '12:00', '12:30');

        $this->actingAs($user);

        $response = $this->get("/attendance/detail/{$attendanceId}");
        $response->assertStatus(200);

        $response->assertSee('12:00');
        $response->assertSee('12:30');

        Carbon::setTestNow();
    }
}
