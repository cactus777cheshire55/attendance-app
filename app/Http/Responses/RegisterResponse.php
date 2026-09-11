<?php

namespace App\Http\Responses;

use App\Mail\HelloWorld;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();

        Log::info('RegisterResponse START', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'is_first_login' => $user?->is_first_login,
        ]);

        if ($user && $user->is_first_login) {

            Log::info('Sending HelloWorld mail');

            Mail::to($user->email)->send(new HelloWorld);

            Log::info('Mail sent');

            $user->is_first_login = false;
            $user->save();

            Log::info('is_first_login changed to false');
        }

        return redirect()->to('/attendance');
    }
}
