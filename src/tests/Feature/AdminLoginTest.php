<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminLoginUser(array $overrides = [])
    {
        return \App\Models\User::factory()->create(array_merge([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));
    }

    private function validAdminLoginData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'admin@example.com',
            'password' => 'password123',
        ], $overrides);
    }

    /** @test */
    public function メールアドレスが未入力の場合_バリデーションメッセージが表示される()
    {
        $this->createAdminLoginUser();

        $data = $this->validAdminLoginData(['email' => '']);

        $response = $this->from('/admin/login')->post('/admin/login', $data);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    /** @test */
    public function パスワードが未入力の場合_バリデーションメッセージが表示される()
    {
        $this->createAdminLoginUser();

        $data = $this->validAdminLoginData(['password' => '']);

        $response = $this->from('/admin/login')->post('/admin/login', $data);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    /** @test */
    public function 登録内容と一致しない場合_バリデーションメッセージが表示される()
    {
        $this->createAdminLoginUser(['email' => 'admin@example.com']);

        $data = $this->validAdminLoginData([
            'email' => 'wrong-admin@example.com',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', $data);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }
}
