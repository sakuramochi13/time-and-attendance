<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        Role::updateOrCreate(['name' => 'employee']);
        Role::updateOrCreate(['name' => 'admin']);
    }
}
