<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function createLoginUser(array $overrides = [])
    {
        return \App\Models\User::factory()->create(array_merge([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));
    }

    private function validLoginData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'login@example.com',
            'password' => 'password123',
        ], $overrides);
    }

    /** @test */
    public function メールアドレスが未入力の場合_バリデーションメッセージが表示される()
    {
        $this->createLoginUser();

        $data = $this->validLoginData(['email' => '']);

        $response = $this->from('/login')->post('/login', $data);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    /** @test */
    public function パスワードが未入力の場合_バリデーションメッセージが表示される()
    {
        $this->createLoginUser();

        $data = $this->validLoginData(['password' => '']);

        $response = $this->from('/login')->post('/login', $data);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    /** @test */
    public function 登録内容と一致しない場合_バリデーションメッセージが表示される()
    {
        $this->createLoginUser(['email' => 'login@example.com']);

        $data = $this->validLoginData([
            'email' => 'wrong@example.com',
        ]);

        $response = $this->from('/login')->post('/login', $data);

        $response->assertRedirect('/login');

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }
}