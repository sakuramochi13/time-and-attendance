<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 会員登録後_認証メールが送信される()
    {
        Notification::fake();

        $data = [
            'name' => '山田 太郎',
            'email' => 'yamada@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $res = $this->post('/register', $data);

        $res->assertRedirect();

        $user = User::where('email', 'yamada@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    /** @test */
    public function メール認証誘導画面で_認証はこちらから_を押下できるリンクが表示される()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->actingAs($user);

        $res = $this->get('/email/verify');
        $res->assertStatus(200);

        $html = $res->getContent();

        $pattern = '/<a[^>]*href="([^"]+)"[^>]*>\s*認証はこちらから\s*<\/a>/u';
        $this->assertMatchesRegularExpression($pattern, $html);

        preg_match($pattern, $html, $m);
        $href = html_entity_decode($m[1]);

        $this->assertStringStartsWith('http://localhost:8025', $href);
    }

    /** @test */
    public function メール認証を完了すると_勤怠登録画面に遷移する()
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 6, 10, 0, 0, 'Asia/Tokyo'));

        $user = User::factory()->create([
            'email' => 'verify@example.com',
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $res = $this->actingAs($user)->get($verificationUrl);

        $res->assertRedirect('/attendance');

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());

        Carbon::setTestNow();
    }
}
