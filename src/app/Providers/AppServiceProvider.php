<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Laravel\Fortify\Contracts\LogoutResponse as FortifyLogoutResponseContract;
use App\Http\Responses\LogoutResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            \Laravel\Fortify\Contracts\LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer([
            'layouts.app',
            'attendance.*',
            'stamp_correction_request.*',
        ], function ($view) {

            if (!Auth::check()) {
                $view->with('nav_is_clocked_out', false);
                return;
            }

            $today = CarbonImmutable::today()->toDateString();

            $todayAttendance = Attendance::query()
                ->where('user_id', Auth::id())
                ->whereDate('work_date', $today)
                ->first(['id', 'clock_out_at']);

            $isClockedOut = !is_null(optional($todayAttendance)->clock_out_at);

            $view->with('nav_is_clocked_out', $isClockedOut);
        });
    }
}
