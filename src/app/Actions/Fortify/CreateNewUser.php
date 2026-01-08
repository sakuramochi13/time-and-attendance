<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\Role;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $formRequest = new RegisterRequest();

        $rules = $formRequest->rules();
        $rules['email'][] = Rule::unique(User::class);

        Validator::make(
            $input,
            $rules,
            $formRequest->messages()
        )->validate();

        return DB::transaction(function () use ($input) {

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $employeeRoleId = Role::where('name', 'employee')->value('id');

            if ($employeeRoleId) {
                $user->roles()->attach($employeeRoleId);
            }

            return $user;
        });

    }
}

