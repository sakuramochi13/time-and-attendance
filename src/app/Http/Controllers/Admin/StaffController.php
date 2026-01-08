<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class StaffController extends Controller
{
        public function index(Request $request)
    {
        $staff = User::query()
            ->orderBy('id')
            ->paginate(25);

        return view('admin.staff.list', compact('staff'));
    }

}
