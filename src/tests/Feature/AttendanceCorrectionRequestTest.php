<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceCorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_FINISHED = 3;

    private function freezeNow(int $year, int $month, int $day, int $hour = 10, int $minute = 0): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        Carbon::setTestNow(Carbon::create($year, $month, $day, $hour, $minute, 0, 'Asia/Tokyo'));
    }

    private function createAttendance(int $userId, string $workDate, string $clockIn, string $clockOut): int
    {
        return DB::table('attendances')->insertGetId([
            'user_id' => $userId,
            'work_date' => $workDate,
            'clock_in_at' => "{$workDate} {$clockIn}:00",
            'clock_out_at' => "{$workDate} {$clockOut}:00",
            'work_status' => self::STATUS_FINISHED,
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

    private function validRequestPayload(array $overrides = []): array
    {
        $base = [
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'breaks' => [
                ['break_start_at' => '12:00', 'break_end_at' => '12:30'],
            ],
            'reason' => 'テスト用の備考です',
        ];

        return array_replace_recursive($base, $overrides);
    }

    private function findFirstDetailHref(string $html): string
    {
        $pattern = '/<a[^>]+href="([^"]+)"[^>]*>\s*詳細\s*<\/a>/u';
        if (!preg_match($pattern, $html, $m)) {
            $this->fail("リンク '詳細' が見つかりませんでした。");
        }

        $href = html_entity_decode(trim($m[1]));
        $parts = parse_url($href);
        $path  = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        return $path . $query;
    }

    /** @test */
    public function 出勤時間が退勤時間より後になっている場合エラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $data = $this->validRequestPayload([
            'clock_in_at' => '19:00',
            'clock_out_at' => '18:00',
        ]);

        $res = $this->from("/attendance/detail/{$attendanceId}")
            ->post("/attendance/detail/{$attendanceId}", $data);

        $res->assertRedirect("/attendance/detail/{$attendanceId}");
        $res->assertSessionHasErrors([
            'clock_in_at' => '出勤時間が不適切な値です',
        ]);

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩開始時間が退勤時間より後になっている場合エラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $data = $this->validRequestPayload([
            'clock_out_at' => '18:00',
            'breaks' => [
                ['start' => '19:00', 'end' => '19:30'],
            ],
        ]);

        $res = $this->from("/attendance/detail/{$attendanceId}")
            ->post("/attendance/detail/{$attendanceId}", $data);

        $res->assertRedirect();

        $location = $res->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringStartsWith("http://localhost/attendance/detail/{$attendanceId}", $location);

        $this->assertDatabaseCount('attendance_corrections', 0);

        $res->assertSessionHasErrors([
            'breaks.0.start' => '休憩時間が不適切な値です',
        ]);

        Carbon::setTestNow();
    }

    /** @test */
    public function 休憩終了時間が退勤時間より後になっている場合エラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $data = $this->validRequestPayload([
            'clock_out_at' => '18:00',
            'breaks' => [
                ['start' => '17:30', 'end' => '19:00'],
            ],
        ]);

        $res = $this->from("/attendance/detail/{$attendanceId}")
            ->post("/attendance/detail/{$attendanceId}", $data);

        $res->assertRedirect();
        $location = $res->headers->get('Location');
        $this->assertStringStartsWith("http://localhost/attendance/detail/{$attendanceId}", $location);

        $this->assertDatabaseCount('attendance_corrections', 0);

        $res->assertSessionHasErrors([
            'breaks.0.end' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);

        Carbon::setTestNow();
    }

    /** @test */
    public function 備考欄が未入力の場合のエラーメッセージが表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $data = $this->validRequestPayload([
            'reason' => '',
        ]);

        $res = $this->from("/attendance/detail/{$attendanceId}")
            ->post("/attendance/detail/{$attendanceId}", $data);

        $res->assertRedirect("/attendance/detail/{$attendanceId}");
        $res->assertSessionHasErrors([
            'reason' => '備考を記入してください',
        ]);

        Carbon::setTestNow();
    }

    /** @test */
    public function 修正申請処理が実行され管理者の承認画面と申請一覧画面に表示される()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create([
            'name' => '一般ユーザーA',
        ]);
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');
        $this->createBreak($attendanceId, '2026-01-06', '12:00', '12:30');

        $this->actingAs($user);

        $data = $this->validRequestPayload([
            'clock_in_at' => '09:15',
            'clock_out_at' => '18:15',
            'breaks' => [
                ['break_start_at' => '12:10', 'break_end_at' => '12:40'],
            ],
            'reason' => '修正申請テスト理由',
        ]);

        $this->post("/attendance/detail/{$attendanceId}", $data)->assertStatus(302);

        $correction = DB::table('attendance_corrections')
            ->where('attendance_id', $attendanceId)
            ->latest('id')
            ->first();

        $this->assertNotNull($correction);

        $admin = $this->createAdminUser(['name' => '管理者']);
        $this->actingAs($admin);

        $approve = $this->get("/stamp_correction_request/approve/{$correction->id}");
        $approve->assertStatus(200);
        $approve->assertSee('修正申請テスト理由');

        $list = $this->get('/stamp_correction_request/list?status=pending');
        $list->assertStatus(200);
        $list->assertSee('修正申請テスト理由');

        Carbon::setTestNow();
    }

    /** @test */
    public function 承認待ちにログインユーザーが行った申請が全て表示されている()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create([
            'name' => '一般ユーザーB',
        ]);
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $this->post("/attendance/detail/{$attendanceId}", $this->validRequestPayload([
            'clock_in_at' => '09:10',
            'reason' => '申請理由1',
        ]))->assertStatus(302);

        $this->post("/attendance/detail/{$attendanceId}", $this->validRequestPayload([
            'clock_in_at' => '09:20',
            'reason' => '申請理由2',
        ]))->assertStatus(302);

        $list = $this->get('/stamp_correction_request/list?status=pending');
        $list->assertStatus(200);

        $list->assertSee('申請理由1');
        $list->assertSee('申請理由2');

        Carbon::setTestNow();
    }

    /** @test */
    public function 承認済みに管理者が承認した修正申請が全て表示されている()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $this->post("/attendance/detail/{$attendanceId}", $this->validRequestPayload([
            'clock_in_at' => '09:30',
            'reason' => '承認済み表示テスト',
        ]))->assertStatus(302);

        $correction = DB::table('attendance_corrections')
            ->where('attendance_id', $attendanceId)
            ->latest('id')
            ->first();

        $this->assertNotNull($correction);

        DB::table('attendance_corrections')
            ->where('id', $correction->id)
            ->update([
                'status' => 1,
                'approved_by_user_id' => $this->createAdminUser()->id,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($user);

        $list = $this->get('/stamp_correction_request/list?status=approved');
        $list->assertStatus(200);
        $list->assertSee('承認済み表示テスト');

        Carbon::setTestNow();
    }

    /** @test */
    public function 各申請の詳細を押下すると勤怠詳細画面に遷移する()
    {
        $this->freezeNow(2026, 1, 6);

        $user = \App\Models\User::factory()->create();
        $attendanceId = $this->createAttendance($user->id, '2026-01-06', '09:00', '18:00');

        $this->actingAs($user);

        $this->post("/attendance/detail/{$attendanceId}", $this->validRequestPayload([
            'clock_in_at' => '09:05',
            'reason' => '詳細遷移テスト',
        ]))->assertStatus(302);

        $list = $this->get('/stamp_correction_request/list?status=pending');
        $list->assertStatus(200);
        $list->assertSee('詳細');

        $detailHref = $this->findFirstDetailHref($list->getContent());

        $detail = $this->get($detailHref);
        $detail->assertStatus(200);
        $detail->assertSee('詳細遷移テスト');

        Carbon::setTestNow();
    }
}

