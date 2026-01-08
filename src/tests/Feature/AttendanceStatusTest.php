<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_OFF      = 0;
    private const STATUS_WORKING  = 1;
    private const STATUS_BREAK    = 2;
    private const STATUS_FINISHED = 3;

    private function fixedToday(): Carbon
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $fixedNow = Carbon::create(2026, 1, 6, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);
        return $fixedNow;
    }

    private function createAttendanceRow(int $userId, array $overrides = []): int
    {
        $today = Carbon::now('Asia/Tokyo')->toDateString();

        return DB::table('attendances')->insertGetId(array_merge([
            'user_id' => $userId,
            'work_date' => $today,
            'clock_in_at' => null,
            'clock_out_at' => null,
            'work_status' => self::STATUS_OFF,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createBreakRow(int $attendanceId, array $overrides = []): void
    {
        DB::table('breaks')->insert(array_merge([
            'attendance_id' => $attendanceId,
            'break_start_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(12, 0),
            'break_end_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @test */
    public function 勤務外の場合_勤怠ステータスが正しく表示される()
    {
        $this->fixedToday();

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');

        Carbon::setTestNow();
    }

    /** @test */
    public function 出勤中の場合_勤怠ステータスが正しく表示される()
    {
        $this->fixedToday();

        $user = \App\Models\User::factory()->create();

        $this->createAttendanceRow($user->id, [
            'clock_in_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(9, 0),
            'clock_out_at' => null,
            'work_status' => self::STATUS_WORKING,
        ]);

        $this->actingAs($user);
        $response = $this->get('/attendance');

        $response->assertStatus(200);

        $response->assertSee('勤務中');

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩中の場合_勤怠ステータスが正しく表示される()
    {
        $this->fixedToday();

        $user = \App\Models\User::factory()->create();

        $attendanceId = $this->createAttendanceRow($user->id, [
            'clock_in_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(9, 0),
            'clock_out_at' => null,
            'work_status' => self::STATUS_BREAK,
        ]);

        $this->createBreakRow($attendanceId, [
            'break_start_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(12, 0),
            'break_end_at' => null,
        ]);

        $this->actingAs($user);
        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');

        Carbon::setTestNow();
    }

    /** @test */
    public function 退勤済の場合_勤怠ステータスが正しく表示される()
    {
        $this->fixedToday();

        $user = \App\Models\User::factory()->create();

        $this->createAttendanceRow($user->id, [
            'clock_in_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(9, 0),
            'clock_out_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(18, 0),
            'work_status' => self::STATUS_FINISHED,
        ]);

        $this->actingAs($user);
        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');

        Carbon::setTestNow();
    }
}