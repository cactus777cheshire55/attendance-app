<?php

namespace App\Http\Responses;

use App\Mail\HelloWorld;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class AuthResponse implements LoginResponseContract, RegisterResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/login');
        }

        if ($user->admin_status) {
            return redirect()->to('/admin/admin-attendance-list');
        }

        if ($user->is_first_login) {
            Mail::to($user->email)->send(new HelloWorld);
            $user->is_first_login = false;
            $user->save();

        }

        return to_route('user.attendance');
    }
}
