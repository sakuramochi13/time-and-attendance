<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\Auth;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        $referer = (string) $request->headers->get('referer', '');

        $path = parse_url($referer, PHP_URL_PATH) ?? '';

        if (str_starts_with($path, '/admin')) {
            return redirect('/admin/login');
        }

        return redirect('/login');
    }
}
