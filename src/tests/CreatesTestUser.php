<?php

namespace Tests;

use App\Models\User;

trait CreatesTestUser
{
    protected function createUser(array $overrides = []): User
    {
        return User::factory()->create($overrides);
    }

    protected function makeUser(array $overrides = []): User
    {
        return User::factory()->make($overrides);
    }

    protected function validUserData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テスト太郎',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }
}
