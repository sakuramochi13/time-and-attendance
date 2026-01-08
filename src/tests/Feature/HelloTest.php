<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelloTest extends TestCase
{
    use RefreshDatabase;

    private function validRegisterData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    /** @test */
    public function 名前が未入力の場合_バリデーションメッセージが表示される()
    {
        $data = $this->validRegisterData(['name' => '']);

        $response = $this->from('/register')->post('/register', $data);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['name']);
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    /** @test */
    public function メールアドレスが未入力の場合_バリデーションメッセージが表示される()
    {
        $data = $this->validRegisterData(['email' => '']);

        $response = $this->from('/register')->post('/register', $data);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /** @test */
    public function パスワードが8文字未満の場合_バリデーションメッセージが表示される()
    {
        $data = $this->validRegisterData([
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]);

        $response = $this->from('/register')->post('/register', $data);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    /** @test */
    public function パスワードが一致しない場合_バリデーションメッセージが表示される()
    {
        $data = $this->validRegisterData([
            'password' => 'password123',
            'password_confirmation' => 'password999',
        ]);

        $response = $this->from('/register')->post('/register', $data);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    /** @test */
    public function パスワードが未入力の場合_バリデーションメッセージが表示される()
    {
        $data = $this->validRegisterData([
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response = $this->from('/register')->post('/register', $data);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    /** @test */
    public function フォームに内容が入力されていた場合_データが正常に保存される()
    {
        $data = $this->validRegisterData([
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
        ]);

        $response = $this->post('/register', $data);

        $response->assertStatus(302);

        $this->assertDatabaseHas('users', [
            'name' => '山田太郎',
            'email' => 'yamada@example.com',
        ]);
    }
}
