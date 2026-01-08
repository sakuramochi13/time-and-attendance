<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private function freezeNow(int $year, int $month, int $day = 10, int $hour = 10, int $minute = 0): Carbon
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $fixedNow = Carbon::create($year, $month, $day, $hour, $minute, 0, 'Asia/Tokyo');
        Carbon::setTestNow($fixedNow);
        return $fixedNow;
    }

    private function createAttendance(int $userId, string $workDate, string $clockIn, string $clockOut, int $workStatus = 3): int
    {
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

    private function findFirstDetailHref(string $html): string
    {
        $pattern = '/<a[^>]+href="([^"]+)"[^>]*>\s*詳細\s*<\/a>/u';

        if (!preg_match($pattern, $html, $m)) {
            $this->fail("リンク '詳細' が見つかりませんでした。");
        }

        return $this->normalizeUrlToPath($m[1]);
    }

    private function findHrefByClass(string $html, string $class): string
    {
        $pattern = '/<a[^>]*class="[^"]*' . preg_quote($class, '/') . '[^"]*"[^>]*href="([^"]+)"[^>]*>/u';

        if (!preg_match($pattern, $html, $m)) {
            $this->fail("リンク class='{$class}' が見つかりませんでした。");
        }

        $href = html_entity_decode(trim($m[1]));

        $parts = parse_url($href);
        $path  = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';

        return $path . $query;
    }

    private function findPrevMonthHref(string $html): string
    {
        return $this->findHrefByClass($html, 'list__calendar-before');
    }

    private function findNextMonthHref(string $html): string
    {
        return $this->findHrefByClass($html, 'list__calendar-after');
    }

    private function normalizeUrlToPath(string $href): string
    {
    $href = trim($href);

    if (str_starts_with($href, '//')) {
        $href = 'http:' . $href;
    }

    if (preg_match('#^https?://#', $href)) {
        $parts = parse_url($href);
        $path  = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        return $path . $query;
    }

    return $href;
}

    /** @test */
    public function 自分が行った勤怠情報が全て表示されている()
    {
        $this->freezeNow(2026, 1, 10);

        $user = \App\Models\User::factory()->create();
        $other = \App\Models\User::factory()->create();

        $this->createAttendance($user->id, '2026-01-03', '09:00', '18:00');
        $this->createAttendance($user->id, '2026-01-04', '10:00', '19:00');
        $this->createAttendance($user->id, '2026-01-05', '08:30', '17:30');

        $this->createAttendance($other->id, '2026-01-04', '07:00', '16:00');

        $this->actingAs($user);
        $response = $this->get('/attendance/list');

        $response->assertStatus(200);

        $response->assertSee('09:00');
        $response->assertSee('10:00');
        $response->assertSee('08:30');

        $response->assertDontSee('07:00');

        Carbon::setTestNow();
    }

    /** @test */
    public function 勤怠一覧画面に遷移した際に現在の月が表示される()
    {
        $fixedNow = $this->freezeNow(2026, 1, 10);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee($fixedNow->format('Y/n'));

        Carbon::setTestNow();
    }

    /** @test */
    public function 前月を押下した時に表示月の前月の情報が表示される()
    {
        $this->freezeNow(2026, 1, 10);

        $user = \App\Models\User::factory()->create();

        $this->createAttendance($user->id, '2025-12-20', '09:15', '18:15');

        $this->actingAs($user);

        $current = $this->get('/attendance/list');
        $current->assertStatus(200);

        $prevHref = $this->findPrevMonthHref($current->getContent());

        $prev = $this->get($prevHref);
        $prev->assertStatus(200);

        $prev->assertSee('09:15');

        Carbon::setTestNow();
    }

    /** @test */
    public function 翌月を押下した時に表示月の翌月の情報が表示される()
    {
        $this->freezeNow(2026, 1, 10);

        $user = \App\Models\User::factory()->create();

        $this->createAttendance($user->id, '2026-02-05', '11:00', '20:00');

        $this->actingAs($user);

        $current = $this->get('/attendance/list');
        $current->assertStatus(200);

        $nextHref = $this->findNextMonthHref($current->getContent());

        $next = $this->get($nextHref);
        $next->assertStatus(200);

        $next->assertSee('11:00');

        Carbon::setTestNow();
    }

    /** @test */
    public function 詳細を押下するとその日の勤怠詳細画面に遷移する()
    {
        $this->freezeNow(2026, 1, 10);

        $user = \App\Models\User::factory()->create();

        $attendanceId = $this->createAttendance($user->id, '2026-01-03', '09:00', '18:00');

        $this->actingAs($user);

        $list = $this->get('/attendance/list');
        $list->assertStatus(200);

        $detailHref = $this->findFirstDetailHref($list->getContent());

        $detail = $this->get($detailHref);

        $detail->assertStatus(200);

        $detail->assertSee('09:00');

        Carbon::setTestNow();
    }
}
