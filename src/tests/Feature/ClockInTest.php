<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClockInTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_OFF      = 0;
    private const STATUS_WORKING  = 1;
    private const STATUS_BREAK    = 2;
    private const STATUS_FINISHED = 3;

    private function freezeNow(): Carbon
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $fixedNow = Carbon::create(2026, 1, 6, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);
        return $fixedNow;
    }

    private function today(): string
    {
        return Carbon::now('Asia/Tokyo')->toDateString();
    }

    /** @test */
    public function 出勤ボタンが正しく機能する()
    {
        $fixedNow = $this->freezeNow();

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');

        $post = $this->post('/attendance/clock-in');

        $post->assertStatus(302);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => $this->today(),
            'work_status' => self::STATUS_WORKING,
        ]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => $this->today(),
            'clock_in_at' => $fixedNow->format('Y-m-d H:i:s'),
        ]);

        $after = $this->get('/attendance');
        $after->assertStatus(200);
        $after->assertSee('勤務中');

        Carbon::setTestNow();
    }

    /** @test */
    public function 出勤は一日一回のみできる_退勤済の場合は出勤ボタンが表示されない()
    {
        $this->freezeNow();

        $user = \App\Models\User::factory()->create();

        \Illuminate\Support\Facades\DB::table('attendances')->insert([
            'user_id' => $user->id,
            'work_date' => $this->today(),
            'clock_in_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'clock_out_at' => Carbon::now('Asia/Tokyo')->copy()->setTime(18, 0)->format('Y-m-d H:i:s'),
            'work_status' => self::STATUS_FINISHED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        $response->assertDontSee('/attendance/clock-in');
    }

    /** @test */
    public function 出勤時刻が勤怠一覧画面で確認できる()
    {
        $fixedNow = $this->freezeNow();

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $this->post('/attendance/clock-in')->assertStatus(302);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee($fixedNow->format('H:i'));

        Carbon::setTestNow();
    }
}
