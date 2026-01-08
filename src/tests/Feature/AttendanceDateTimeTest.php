<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class AttendanceDateTimeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 現在の日時情報がUIと同じ形式で出力されている()
    {
        App::setLocale('ja');
        Carbon::setLocale('ja');

        config(['app.timezone' => 'Asia/Tokyo']);

        $fixedNow = Carbon::create(2026, 1, 6, 10, 0, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/attendance');

        $response->assertStatus(200);

        $expected = $fixedNow->isoFormat('YYYY年M月D日(ddd)');

        $response->assertSee($expected);

        Carbon::setTestNow();
    }
}