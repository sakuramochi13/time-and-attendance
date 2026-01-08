<?php

namespace Tests\Feature;

use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Role;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\CorrectionBreak;
use App\Models\BreakTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class AdminCorrectionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function freezeNow(int $y, int $m, int $d, int $h = 10, int $i = 0, int $s = 0): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        Carbon::setTestNow(Carbon::create($y, $m, $d, $h, $i, $s, 'Asia/Tokyo'));
    }

    private function ensureRole(string $name): Role
    {
        return Role::query()->firstOrCreate(['name' => $name]);
    }

    private function createAdminUser(array $overrides = []): User
    {
        $admin = User::factory()->create(array_merge([
            'name'  => '管理者 太郎',
            'email' => 'admin@example.com',
        ], $overrides));

        $admin->roles()->attach($this->ensureRole('admin')->id);

        return $admin;
    }

    private function createEmployeeUser(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'name'  => '一般 太郎',
            'email' => 'employee' . uniqid() . '@example.com',
        ], $overrides));

        $user->roles()->attach($this->ensureRole('employee')->id);

        return $user;
    }

    private function createAttendance(User $user, string $workDate, ?string $clockIn = null, ?string $clockOut = null, int $workStatus = 0): Attendance
    {
        return Attendance::query()->create([
            'user_id'      => $user->id,
            'work_date'    => $workDate,
            'clock_in_at'  => $clockIn  ? Carbon::parse($workDate . ' ' . $clockIn, 'Asia/Tokyo') : null,
            'clock_out_at' => $clockOut ? Carbon::parse($workDate . ' ' . $clockOut, 'Asia/Tokyo') : null,
            'work_status'  => $workStatus,
        ]);
    }

    private function createBreak(Attendance $attendance, string $workDate, string $start, string $end): BreakTime
    {
        return BreakTime::query()->create([
            'attendance_id'   => $attendance->id,
            'break_start_at'  => Carbon::parse($workDate . ' ' . $start, 'Asia/Tokyo'),
            'break_end_at'    => Carbon::parse($workDate . ' ' . $end, 'Asia/Tokyo'),
        ]);
    }

    private function createCorrection(
        Attendance $attendance,
        string $workDate,
        string $reqIn,
        string $reqOut,
        string $reason,
        int $status = 0,
        ?User $approvedBy = null,
        array $breaks = []
    ): AttendanceCorrection {
        $correction = AttendanceCorrection::query()->create([
            'attendance_id'            => $attendance->id,
            'requested_clock_in_at'    => Carbon::parse($workDate . ' ' . $reqIn, 'Asia/Tokyo'),
            'requested_clock_out_at'   => Carbon::parse($workDate . ' ' . $reqOut, 'Asia/Tokyo'),
            'reason'                   => $reason,
            'status'                   => $status,
            'approved_by_user_id'      => $approvedBy?->id,
            'approved_at'              => $approvedBy ? Carbon::now() : null,
        ]);

        foreach ($breaks as $b) {
            CorrectionBreak::query()->create([
                'attendance_correction_id' => $correction->id,
                'break_start_at'           => Carbon::parse($workDate . ' ' . $b['start'], 'Asia/Tokyo'),
                'break_end_at'             => Carbon::parse($workDate . ' ' . $b['end'], 'Asia/Tokyo'),
            ]);
        }

        return $correction;
    }

    private function findHrefByAnchorText(string $html, string $label): string
    {
        $pattern = '/<a[^>]+href="([^"]+)"[^>]*>\s*' . preg_quote($label, '/') . '\s*(?:<[^>]*>\s*)*<\/a>/u';

        if (!preg_match($pattern, $html, $m)) {
            $this->fail("リンク '{$label}' が見つかりませんでした。");
        }

        return html_entity_decode(trim($m[1]));
    }

    private function findFirstDetailHref(string $html): string
    {
        $pattern = '/<a[^>]*class="[^"]*correction-request__table-cell--detail[^"]*"[^>]*href="([^"]+)"/u';

        if (!preg_match($pattern, $html, $m)) {
            $pattern2 = '/<a[^>]+href="([^"]+)"[^>]*>\s*詳細\s*<\/a>/u';
            if (!preg_match($pattern2, $html, $m2)) {
                $this->fail("詳細リンクが見つかりませんでした。");
            }
            return html_entity_decode(trim($m2[1]));
        }

        return html_entity_decode(trim($m[1]));
    }

    private function findApproveForm(string $html): array
    {
        if (!preg_match_all('/<form\b[^>]*>.*?<\/form>/isu', $html, $allForms)) {
            $this->fail("formタグが見つかりませんでした。");
        }

        $forms = $allForms[0];

        foreach ($forms as $formHtml) {
            if (!preg_match('/\saction="([^"]+)"/i', $formHtml, $am)) continue;

            $action = html_entity_decode(trim($am[1]));
            if (stripos($action, 'approve') === false) continue;

            $method = 'POST';
            if (preg_match('/\smethod="([^"]+)"/i', $formHtml, $mm)) {
                $method = strtoupper(trim($mm[1]));
            }

            $spoof = null;
            if (preg_match('/name="_method"\s+value="(PUT|PATCH|DELETE)"/i', $formHtml, $sm)) {
                $spoof = strtoupper($sm[1]);
            }

            return [$action, $method, $spoof];
        }

        foreach ($forms as $formHtml) {
            if (mb_strpos($formHtml, '承認') === false) continue;
            if (!preg_match('/\saction="([^"]+)"/i', $formHtml, $am)) continue;

            $action = html_entity_decode(trim($am[1]));

            $method = 'POST';
            if (preg_match('/\smethod="([^"]+)"/i', $formHtml, $mm)) {
                $method = strtoupper(trim($mm[1]));
            }

            $spoof = null;
            if (preg_match('/name="_method"\s+value="(PUT|PATCH|DELETE)"/i', $formHtml, $sm)) {
                $spoof = strtoupper($sm[1]);
            }

            return [$action, $method, $spoof];
        }

        $actions = [];
        foreach ($forms as $formHtml) {
            if (preg_match('/\saction="([^"]+)"/i', $formHtml, $am)) {
                $actions[] = html_entity_decode(trim($am[1]));
            }
        }

        $this->fail("承認フォームが見つかりませんでした。form action一覧: " . implode(' | ', $actions));
    }


    /** @test */
    public function 承認待ちの修正申請が全て表示されている(): void
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();

        $u1 = $this->createEmployeeUser(['name' => '山田 太郎', 'email' => 'yamada@example.com']);
        $u2 = $this->createEmployeeUser(['name' => '西 伶奈',   'email' => 'nishi@example.com']);

        $a1 = $this->createAttendance($u1, '2026-01-06', '09:00', '18:00', 1);
        $a2 = $this->createAttendance($u2, '2026-01-06', '09:30', '18:30', 1);

        $this->createCorrection($a1, '2026-01-06', '09:10', '18:10', '電車遅延のため', 0, null, [
            ['start' => '12:00', 'end' => '12:30'],
        ]);
        $this->createCorrection($a2, '2026-01-06', '09:40', '18:40', '打刻漏れ', 0);

        $this->createCorrection($a1, '2026-01-06', '09:05', '18:05', '別申請（承認済）', 1, $admin);

        $this->actingAs($admin);

        $res = $this->get('/stamp_correction_request/list?status=pending');
        $res->assertStatus(200);

        $res->assertSee('山田 太郎');
        $res->assertSee('西 伶奈');

        $res->assertDontSee('別申請（承認済）');

        Carbon::setTestNow();
    }

    /** @test */
    public function 承認済みの修正申請が全て表示されている(): void
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();

        $u1 = $this->createEmployeeUser(['name' => '山田 太郎', 'email' => 'yamada@example.com']);
        $a1 = $this->createAttendance($u1, '2026-01-06', '09:00', '18:00', 1);

        $this->createCorrection($a1, '2026-01-06', '09:05', '18:05', '承認済テスト', 1, $admin);

        $this->createCorrection($a1, '2026-01-06', '09:10', '18:10', '承認待ちテスト', 0);

        $this->actingAs($admin);

        $res = $this->get('/stamp_correction_request/list?status=approved');
        $res->assertStatus(200);

        $res->assertSee('承認済テスト');
        $res->assertDontSee('承認待ちテスト');

        Carbon::setTestNow();
    }

    /** @test */
    public function 修正申請の詳細内容が正しく表示されている(): void
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();

        $u1 = $this->createEmployeeUser(['name' => '山田 太郎', 'email' => 'yamada@example.com']);
        $attendance = $this->createAttendance($u1, '2026-01-06', '09:00', '18:00', 1);

        $this->createCorrection($attendance, '2026-01-06', '09:10', '18:10', '電車遅延のため', 0, null, [
            ['start' => '12:00', 'end' => '12:30'],
        ]);

        $this->actingAs($admin);

        $list = $this->get('/stamp_correction_request/list?status=pending');
        $list->assertStatus(200);

        $detailHref = $this->findFirstDetailHref($list->getContent());
        $detail = $this->get($detailHref);
        $detail->assertStatus(200);

        $detail->assertSee('山田 太郎');
        $detail->assertSee('電車遅延のため');
        $detail->assertSee('09:10');
        $detail->assertSee('18:10');
        $detail->assertSee('12:00');
        $detail->assertSee('12:30');

        Carbon::setTestNow();
    }

    /** @test */
    public function 修正申請の承認処理が正しく行われ勤怠情報が更新される(): void
    {
        $this->freezeNow(2026, 1, 6);

        $admin = $this->createAdminUser();

        $u1 = $this->createEmployeeUser(['name' => '山田 太郎', 'email' => 'yamada@example.com']);
        $attendance = $this->createAttendance($u1, '2026-01-06', '09:00', '18:00', 1);

        $correction = $this->createCorrection($attendance, '2026-01-06', '09:10', '18:10', '承認テスト', 0, null, [
            ['start' => '12:00', 'end' => '12:30'],
        ]);

        $this->createBreak($attendance, '2026-01-06', '12:05', '12:20');

        $this->actingAs($admin);

        $list = $this->get('/stamp_correction_request/list?status=pending');
        $list->assertStatus(200);

        $detailHref = $this->findFirstDetailHref($list->getContent());
        $detail = $this->get($detailHref);
        $detail->assertStatus(200);

        [$action, $method, $spoof] = $this->findApproveForm($detail->getContent());

        $payload = [];
        if ($spoof) {
            $payload['_method'] = $spoof;
        }

        $approveRes = $this->post($action, $payload);
        $approveRes->assertRedirect();

        $this->assertDatabaseHas('attendance_corrections', [
            'id'     => $correction->id,
            'status' => 1,
        ]);

        $updated = Attendance::query()->findOrFail($attendance->id);
        $this->assertSame('09:10', optional($updated->clock_in_at)->format('H:i'));
        $this->assertSame('18:10', optional($updated->clock_out_at)->format('H:i'));

        $break = BreakTime::query()
            ->where('attendance_id', $attendance->id)
            ->orderBy('break_start_at')
            ->first();

        $this->assertNotNull($break);
        $this->assertSame('12:00', optional($break->break_start_at)->format('H:i'));
        $this->assertSame('12:30', optional($break->break_end_at)->format('H:i'));

        Carbon::setTestNow();
    }
}
