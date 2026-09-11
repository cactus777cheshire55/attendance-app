<?php

namespace App\Http\Controllers;

use App\Mail\VerificationMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SendMailController extends Controller
{
    public function send(): RedirectResponse
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return back()->with('message', 'メールアドレスはすでに認証済みです。');
        }

        Mail::to($user->email)->send(
            new VerificationMail($user)
        );

        return back()->with(
            'message',
            '認証メールを再送しました。'
        );
    }
}
