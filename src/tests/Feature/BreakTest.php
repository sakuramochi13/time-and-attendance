<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\Attendance;


class BreakTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_OFF      = 0;
    private const STATUS_WORKING  = 1;
    private const STATUS_BREAK    = 2;
    private const STATUS_FINISHED = 3;

    private function freezeNow(int $hour = 10, int $minute = 0): Carbon
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
    public function 休憩ボタンが正しく機能する()
    {
        $this->freezeNow(10, 0);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createWorkingAttendance($user->id);
        $this->actingAs($user);

        $before = $this->get('/attendance');
        $before->assertStatus(200);
        $before->assertSee('休憩入');

        $this->post('/attendance/break-start')->assertStatus(302);

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendanceId,
            'break_end_at' => null,
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendanceId,
            'work_status' => self::STATUS_BREAK,
        ]);

        $after = $this->get('/attendance');
        $after->assertStatus(200);
        $after->assertSee('休憩中');

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩は一日に何回でもできる()
    {
        $this->freezeNow(12, 0);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createWorkingAttendance($user->id);
        $this->actingAs($user);

        $this->post('/attendance/break-start')->assertStatus(302);

        $this->freezeNow(12, 30);
        $this->post('/attendance/break-end')->assertStatus(302);

        $screen = $this->get('/attendance');
        $screen->assertStatus(200);
        $screen->assertSee('休憩入');

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendanceId,
        ]);
        $this->assertDatabaseMissing('breaks', [
            'attendance_id' => $attendanceId,
            'break_end_at' => null,
        ]);

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩戻ボタンが正しく機能する()
    {
        $this->freezeNow(12, 0);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createWorkingAttendance($user->id);
        $this->actingAs($user);

        $this->post('/attendance/break-start')->assertStatus(302);

        $mid = $this->get('/attendance');
        $mid->assertStatus(200);
        $mid->assertSee('休憩戻');

        $this->freezeNow(12, 30);
        $this->post('/attendance/break-end')->assertStatus(302);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendanceId,
            'work_status' => self::STATUS_WORKING,
        ]);

        $after = $this->get('/attendance');
        $after->assertStatus(200);
        $after->assertSee('勤務中');

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩戻は一日に何回でもできる()
    {
        $this->freezeNow(12, 0);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createWorkingAttendance($user->id);
        $this->actingAs($user);

        $this->post('/attendance/break-start')->assertStatus(302);

        $this->freezeNow(12, 30);
        $this->post('/attendance/break-end')->assertStatus(302);

        $this->freezeNow(15, 0);
        $this->post('/attendance/break-start')->assertStatus(302);

        $screen = $this->get('/attendance');
        $screen->assertStatus(200);
        $screen->assertSee('休憩戻');

        $this->assertSame(
            2,
            DB::table('breaks')->where('attendance_id', $attendanceId)->count()
        );

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩時刻が勤怠一覧画面で確認できる()
    {
        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createWorkingAttendance($user->id);

        $this->actingAs($user);

        $attendance = Attendance::findOrFail($attendanceId);
        $workDate = $attendance->work_date;

        \Carbon\Carbon::setTestNow($workDate->copy()->setTime(12, 0, 0));
        $this->post('/attendance/break-start')->assertStatus(302);

        \Carbon\Carbon::setTestNow($workDate->copy()->setTime(12, 30, 0));
        $this->post('/attendance/break-end')->assertStatus(302);

        $month = $workDate->format('Y-m');
        $list = $this->get("/attendance/list?month={$month}");
        $list->assertOk();
        $list->assertSee($workDate->isoFormat('MM/DD(ddd)'));
        $list->assertSee('0:30');

        $this->assertDatabaseHas('breaks', [
            'attendance_id'  => $attendanceId,
            'break_start_at' => $workDate->copy()->setTime(12, 0, 0)->format('Y-m-d H:i:s'),
            'break_end_at'   => $workDate->copy()->setTime(12, 30, 0)->format('Y-m-d H:i:s'),
        ]);

        Carbon::setTestNow();
    }
}
