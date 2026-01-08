<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use App\Http\Requests\LoginRequest as CustomLoginRequest;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \Laravel\Fortify\Http\Requests\LoginRequest::class,
            \App\Http\Requests\LoginRequest::class
        );

        $this->app->singleton(LoginResponseContract::class, CustomLoginResponse::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::registerView(function () {
            return view('auth.register');
        });

        Fortify::loginView(function (Request $request) {
            if ($request->is('admin/login')) {
                return view('admin.login');
            }
            return view('auth.login');
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;

            return Limit::perMinute(10)->by($email . $request->ip());
        });

        Fortify::authenticateUsing(function (Request $request) {
            $input = $request->only('email', 'password');

            Validator::make(
                $input,
                (new \App\Http\Requests\LoginRequest())->rules(),
                (new \App\Http\Requests\LoginRequest())->messages()
            )->validate();

            $user = \App\Models\User::where('email', $input['email'])->first();

            if (! $user || ! Hash::check($input['password'], $user->password)) {
                throw ValidationException::withMessages([
                'email' => ['ログイン情報が登録されていません'],
                ]);
            }

            if ($request->input('login_type') === 'admin') {
                $isAdmin = $user->roles()->where('name', 'admin')->exists();

                if (! $isAdmin) {
                    throw ValidationException::withMessages([
                        'email' => ['管理者権限がありません'],
                    ]);
                }
            }

            return $user;
        });
    }
}
