<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::transaction(function () {

            $employeeRoleId = Role::where('name', 'employee')->value('id');
            $adminRoleId    = Role::where('name', 'admin')->value('id');

            $password = Hash::make('password123');

            $users = [
                [
                    'name'  => '後池 哲子',
                    'email' => 'tetsuko.k@coachtech.com',
                    'roles' => ['employee', 'admin'],
                ],
                [
                    'name'  => '西 伶奈',
                    'email' => 'reina.n@coachtech.com',
                    'roles' => ['employee'],
                ],
                [
                    'name'  => '山田 太郎',
                    'email' => 'taro.y@coachtech.com',
                    'roles' => ['employee'],
                ],
                [
                    'name'  => '増田 一世',
                    'email' => 'issei.m@coachtech.com',
                    'roles' => ['employee'],
                ],
                [
                    'name'  => '山本 敬吉',
                    'email' => 'keikichi.y@coachtech.com',
                    'roles' => ['employee'],
                ],
                [
                    'name'  => '秋田 朋美',
                    'email' => 'tomomi.a@coachtech.com',
                    'roles' => ['employee'],
                ],
                [
                    'name'  => '中西 教夫',
                    'email' => 'norio.n@coachtech.com',
                    'roles' => ['employee'],
                ],
            ];

            foreach ($users as $data) {
                $user = User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => $password,
                        'email_verified_at' => now(),
                    ]
                );

                $roleIds = [];

                if (in_array('employee', $data['roles'], true)) {
                    $roleIds[] = $employeeRoleId;
                }

                if (in_array('admin', $data['roles'], true)) {
                    $roleIds[] = $adminRoleId;
                }

                if (!empty($roleIds)) {
                    $user->roles()->syncWithoutDetaching($roleIds);
                }
            }
        });
    }
}
