<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private function freezeNow(int $year, int $month, int $day, int $hour = 10, int $minute = 0): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        Carbon::setTestNow(Carbon::create($year, $month, $day, $hour, $minute, 0, 'Asia/Tokyo'));
    }

    private function ensureRole(string $name): int
    {
        $existing = DB::table('roles')->where('name', $name)->first();
        if ($existing) return (int) $existing->id;

        return DB::table('roles')->insertGetId(['name' => $name]);
    }

    private function attachRole(int $userId, string $roleName): void
    {
        $roleId = $this->ensureRole($roleName);

        $exists = DB::table('role_user')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if (!$exists) {
            DB::table('role_user')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }

    private function createAdminUser(array $overrides = []): \App\Models\User
    {
        $admin = \App\Models\User::factory()->create($overrides);
        $this->attachRole($admin->id, 'admin');
        return $admin;
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

    private function findHrefByAnchorText(string $html, string $label): string
    {
        $pattern = '/<a[^>]*href="([^"]+)"[^>]*>.*?' . preg_quote($label, '/') . '.*?<\/a>/su';

        if (!preg_match($pattern, $html, $m)) {
            $this->fail("リンク '{$label}' が見つかりませんでした。");
        }

        $href = html_entity_decode(trim($m[1]));
        $parts = parse_url($href);
        $path  = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        return $path . $query;
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

    /** @test */
    public function その日になされた全ユーザーの勤怠情報が正確に確認できる()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser(['name' => '管理者']);

        $user1 = \App\Models\User::factory()->create(['name' => '山田 太郎']);
        $user2 = \App\Models\User::factory()->create(['name' => '西 伶奈']);

        $this->attachRole($user1->id, 'employee');
        $this->attachRole($user2->id, 'employee');

        $this->createAttendance($user1->id, '2026-01-06', '09:10', '18:10');
        $this->createAttendance($user2->id, '2026-01-06', '10:00', '19:00');

        $this->actingAs($admin);

        $res = $this->get('/admin/attendance/list');
        $res->assertStatus(200);

        $res->assertSee('山田 太郎');
        $res->assertSee('09:10');
        $res->assertSee('18:10');

        $res->assertSee('西 伶奈');
        $res->assertSee('10:00');
        $res->assertSee('19:00');

        Carbon::setTestNow();
    }

    /** @test */
    public function 遷移した際に現在の日付が表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $res = $this->get('/admin/attendance/list');
        $res->assertStatus(200);

        $res->assertSee('2026年1月6日の勤怠');

        Carbon::setTestNow();
    }

    /** @test */
    public function 前日を押下した時に前の日の勤怠情報が表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser(['name' => '管理者']);
        $user = \App\Models\User::factory()->create(['name' => '前日ユーザー']);
        $this->attachRole($user->id, 'employee');

        $this->createAttendance($user->id, '2026-01-05', '08:30', '17:30');

        $this->actingAs($admin);

        $current = $this->get('/admin/attendance/list');
        $current->assertStatus(200);

        $prevHref = $this->findHrefByClass($current->getContent(), 'list__calendar-before');
        $prev = $this->get($prevHref);
        $prev->assertStatus(200);

        $prev->assertSee('2026年');
        $prev->assertSee('1月');
        $prev->assertSee('5日');

        $prev->assertSee('前日ユーザー');
        $prev->assertSee('08:30');
        $prev->assertSee('17:30');

        Carbon::setTestNow();
    }

    /** @test */
    public function 翌日を押下した時に次の日の勤怠情報が表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser(['name' => '管理者']);
        $user = \App\Models\User::factory()->create(['name' => '翌日ユーザー']);
        $this->attachRole($user->id, 'employee');

        $this->createAttendance($user->id, '2026-01-07', '11:00', '20:00');

        $this->actingAs($admin);

        $current = $this->get('/admin/attendance/list');
        $current->assertStatus(200);

        $nextHref = $this->findHrefByClass($current->getContent(), 'list__calendar-after');
        $next = $this->get($nextHref);
        $next->assertStatus(200);

        $next->assertSee('2026年');
        $next->assertSee('1月');
        $next->assertSee('7日');

        $next->assertSee('翌日ユーザー');
        $next->assertSee('11:00');
        $next->assertSee('20:00');

        Carbon::setTestNow();
    }
}
