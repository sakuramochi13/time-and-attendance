<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
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

    private function createStaffUser(array $overrides = []): \App\Models\User
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


    private function validUpdatePayload(array $overrides = []): array
    {
        return array_merge([
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'breaks' => [
                ['break_start_at' => '12:00', 'break_end_at' => '13:00'],
            ],
            'reason' => '管理者による修正理由',
        ], $overrides);
    }


    private function adminDetailUrl(int $attendanceId): string
    {
        return "/admin/attendance/{$attendanceId}";
    }

    private function adminUpdateUrl(int $attendanceId): string
    {
        return "/admin/attendance/{$attendanceId}";
    }

    /** @test */
    public function 勤怠詳細画面に表示されるデータが選択したものになっている()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser(['name' => '管理者']);
        $staff = $this->createStaffUser(['name' => '山田 太郎']);

        $attendanceId = $this->createAttendance($staff->id, '2026-01-06', '09:10', '18:10', 3);
        $this->createBreak($attendanceId, '2026-01-06', '12:00', '12:30');

        $this->actingAs($admin);

        $res = $this->get($this->adminDetailUrl($attendanceId));
        $res->assertStatus(200);

        $res->assertSee('山田 太郎');

        $res->assertSee('2026年');
        $res->assertSee('1月');
        $res->assertSee('6日');

        $res->assertSee('09:10');
        $res->assertSee('18:10');

        $res->assertSee('12:00');
        $res->assertSee('12:30');

        Carbon::setTestNow();
    }

    /** @test */
    public function 出勤時間が退勤時間より後になっている場合エラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();

        $attendanceId = $this->createAttendance($staff->id, '2026-01-06', '09:00', '18:00', 1);

        $this->actingAs($admin);

        $data = $this->validUpdatePayload([
            'clock_in_at' => '19:00',
            'clock_out_at' => '18:00',
        ]);

        $res = $this->from($this->adminDetailUrl($attendanceId))
            ->put($this->adminUpdateUrl($attendanceId), $data);

        $res->assertRedirect();

        $location = $res->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString($this->adminDetailUrl($attendanceId), $location);

        $res->assertSessionHasErrors([
        ]);

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString(
            '出勤時間もしくは退勤時間が不適切な値です',
            implode("\n", $errors->all())
        );

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩開始時間が退勤時間より後になっている場合エラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();

        $attendanceId = $this->createAttendance($staff->id, '2026-01-06', '09:00', '18:00', 1);

        $this->actingAs($admin);

        $data = $this->validUpdatePayload([
            'clock_out_at' => '18:00',
            'breaks' => [
                ['break_start_at' => '19:00', 'break_end_at' => '19:30'],
            ],
        ]);

        $res = $this->from($this->adminDetailUrl($attendanceId))
            ->put($this->adminUpdateUrl($attendanceId), $data);

        $res->assertRedirect();
        $res->assertSessionHasErrors();

        $errors = $res->baseResponse->getSession()->get('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString(
            '休憩時間が不適切な値です',
            implode("\n", $errors->all())
        );
    }

    /** @test */
    public function 休憩終了時間が退勤時間より後になっている場合エラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();

        $attendanceId = $this->createAttendance($staff->id, '2026-01-06', '09:00', '18:00', 1);

        $this->actingAs($admin);

        $data = $this->validUpdatePayload([
            'clock_out_at' => '18:00',
            'breaks' => [
                ['break_start_at' => '17:30', 'break_end_at' => '19:00'],
            ],
        ]);

        $res = $this->from($this->adminDetailUrl($attendanceId))
            ->put($this->adminUpdateUrl($attendanceId), $data);

        $res->assertRedirect();
        $res->assertSessionHasErrors();

        $errors = $res->baseResponse->getSession()->get('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString(
            '休憩時間もしくは退勤時間が不適切な値です',
            implode("\n", $errors->all())
        );
    }

    /** @test */
    public function 備考欄が未入力の場合のエラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();

        $attendanceId = $this->createAttendance($staff->id, '2026-01-06', '09:00', '18:00', 1);

        $this->actingAs($admin);

        $data = $this->validUpdatePayload([
            'reason' => '',
        ]);

        $res = $this->from($this->adminDetailUrl($attendanceId))
            ->put($this->adminUpdateUrl($attendanceId), $data);

        $res->assertRedirect();

        $res->assertSessionHasErrors([
            'reason' => '備考を記入してください',
        ]);

        Carbon::setTestNow();
    }
}

