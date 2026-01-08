<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminStaffTest extends TestCase
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
        $admin = \App\Models\User::factory()->create(array_merge([
            'name'  => '管理者',
            'email' => 'admin@example.com',
        ], $overrides));

        $this->attachRole($admin->id, 'admin');
        return $admin;
    }

    private function createEmployeeUser(array $overrides = []): \App\Models\User
    {
        $user = \App\Models\User::factory()->create($overrides);
        $this->attachRole($user->id, 'employee');
        return $user;
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

    private function createBreak(int $attendanceId, string $workDate, string $start, string $end): int
    {
        return DB::table('breaks')->insertGetId([
            'attendance_id' => $attendanceId,
            'break_start_at' => "{$workDate} {$start}:00",
            'break_end_at' => "{$workDate} {$end}:00",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function staffListUrl(): string
    {
        return '/admin/staff/list';
    }

    private function staffAttendanceUrl(int $userId, ?string $month = null): string
    {
        $base = "/admin/attendance/staff/{$userId}";
        return $month ? "{$base}?month={$month}" : $base;
    }

    private function findFirstAdminAttendanceDetailHref(string $html): string
    {
        if (!preg_match('#href="([^"]*/admin/attendance/\d+[^"]*)"#u', $html, $m)) {
            if (!preg_match('#href="(/admin/attendance/\d+[^"]*)"#u', $html, $m2)) {
                $this->fail('勤怠詳細リンク（/admin/attendance/{id}）が見つかりませんでした。');
            }
            return html_entity_decode($m2[1]);
        }

        $href = html_entity_decode($m[1]);

        $parsed = parse_url($href);
        if (is_array($parsed) && isset($parsed['path'])) {
            $path = $parsed['path'];
            $query = $parsed['query'] ?? null;
            return $query ? "{$path}?{$query}" : $path;
        }

        return $href;
    }

    /** @test */
    public function 管理者ユーザーが全一般ユーザーの氏名メールアドレスを確認できる()
    {
        $this->freezeNow(2026, 1, 10);

        $admin = $this->createAdminUser();

        $e1 = $this->createEmployeeUser([
            'name' => '山田 太郎',
            'email' => 'taro@example.com',
        ]);
        $e2 = $this->createEmployeeUser([
            'name' => '西 伶奈',
            'email' => 'rena@example.com',
        ]);

        $this->actingAs($admin);

        $res = $this->get($this->staffListUrl());
        $res->assertStatus(200);

        $res->assertSee('山田 太郎');
        $res->assertSee('taro@example.com');
        $res->assertSee('西 伶奈');
        $res->assertSee('rena@example.com');

        Carbon::setTestNow();
    }

    /** @test */
    public function ユーザーの勤怠情報が正しく表示される()
    {
        $this->freezeNow(2026, 1, 10);

        $admin = $this->createAdminUser();
        $emp = $this->createEmployeeUser([
            'name' => '山田 太郎',
            'email' => 'taro@example.com',
        ]);

        $attendanceId = $this->createAttendance($emp->id, '2026-01-06', '09:10', '18:10', 3);
        $this->createBreak($attendanceId, '2026-01-06', '12:00', '12:30');

        $this->actingAs($admin);

        $res = $this->get($this->staffAttendanceUrl($emp->id));
        $res->assertStatus(200);

        $res->assertSee('山田 太郎');
        $res->assertSee('09:10');
        $res->assertSee('18:10');

        $res->assertSee('0:30');

        $res->assertSee('8:30');

        Carbon::setTestNow();
    }

    /** @test */
    public function 前月を押下した時に表示月の前月の情報が表示される()
    {
        $this->freezeNow(2026, 1, 10);

        $admin = $this->createAdminUser();
        $emp = $this->createEmployeeUser(['name' => '山田 太郎']);

        $attendanceId = $this->createAttendance($emp->id, '2025-12-20', '09:15', '18:15', 3);
        $this->createBreak($attendanceId, '2025-12-20', '12:00', '12:45');

        $this->actingAs($admin);

        $res = $this->get($this->staffAttendanceUrl($emp->id, '2025-12'));
        $res->assertStatus(200);

        $res->assertSee('09:15');
        $res->assertSee('18:15');

        $res->assertSee('0:45');

        $res->assertSee('8:15');

        Carbon::setTestNow();
    }

    /** @test */
    public function 翌月を押下した時に表示月の翌月の情報が表示される()
    {
        $this->freezeNow(2026, 1, 10);

        $admin = $this->createAdminUser();
        $emp = $this->createEmployeeUser(['name' => '山田 太郎']);

        $attendanceId = $this->createAttendance($emp->id, '2026-02-05', '11:00', '20:00', 3);
        $this->createBreak($attendanceId, '2026-02-05', '15:00', '15:30');

        $this->actingAs($admin);

        $res = $this->get($this->staffAttendanceUrl($emp->id, '2026-02'));
        $res->assertStatus(200);

        $res->assertSee('11:00');
        $res->assertSee('20:00');

        $res->assertSee('0:30');

        $res->assertSee('8:30');

        Carbon::setTestNow();
    }

    /** @test */
    public function 詳細を押下するとその日の勤怠詳細画面に遷移する()
    {
        $this->freezeNow(2026, 1, 10);

        $admin = $this->createAdminUser();
        $emp = $this->createEmployeeUser(['name' => '山田 太郎']);

        $attendanceId = $this->createAttendance($emp->id, '2026-01-06', '09:10', '18:10', 3);

        $this->actingAs($admin);

        $list = $this->get($this->staffAttendanceUrl($emp->id, '2026-01'));
        $list->assertStatus(200);

        $detailHref = $this->findFirstAdminAttendanceDetailHref($list->getContent());

        $detail = $this->get($detailHref);
        $detail->assertStatus(200);

        $detail->assertSee('09:10');
        $detail->assertSee('18:10');

        Carbon::setTestNow();
    }
}
