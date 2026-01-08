<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_OFF      = 0;
    private const STATUS_WORKING  = 1;
    private const STATUS_BREAK    = 2;
    private const STATUS_FINISHED = 3;

    private function freezeNow(int $hour, int $minute): Carbon
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $fixedNow = Carbon::create(2026, 1, 6, $hour, $minute, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);
        return $fixedNow;
    }

    private function today(): string
    {
        return Carbon::now('Asia/Tokyo')->toDateString();
    }

    private function createWorkingAttendance(int $userId, ?Carbon $clockIn = null): int
    {
        $clockIn ??= Carbon::now('Asia/Tokyo')->copy()->setTime(9, 0);

        return DB::table('attendances')->insertGetId([
            'user_id' => $userId,
            'work_date' => $this->today(),
            'clock_in_at' => $clockIn->format('Y-m-d H:i:s'),
            'clock_out_at' => null,
            'work_status' => self::STATUS_WORKING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function 退勤ボタンが正しく機能する()
    {
        $this->freezeNow(10, 0);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createWorkingAttendance($user->id);
        $this->actingAs($user);

        $before = $this->get('/attendance');
        $before->assertStatus(200);
        $before->assertSee('退勤');
        $before->assertSee('/attendance/clock-out');

        $this->freezeNow(18, 0);
        $this->post('/attendance/clock-out')->assertStatus(302);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendanceId,
            'work_status' => self::STATUS_FINISHED,
            'clock_out_at' => Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s'),
        ]);

        $after = $this->get('/attendance');
        $after->assertStatus(200);
        $after->assertSee('退勤済');

        Carbon::setTestNow();
    }

    /** @test */
    public function 退勤時刻が勤怠一覧画面で確認できる()
    {
        $this->freezeNow(9, 0);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->post('/attendance/clock-in')->assertStatus(302);

        $this->freezeNow(18, 0);
        $this->post('/attendance/clock-out')->assertStatus(302);

        $list = $this->get('/attendance/list');
        $list->assertStatus(200);
        $list->assertSee('18:00');

        Carbon::setTestNow();
    }
}
