<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminRedirect
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->admin_status) {
            if ($request->is('attendance/list')) {
                return redirect('/admin/attendance/list');
            }
            if ($request->is('stamp_correction_request/list')) {
                return redirect('/admin/stamp_correction_request/list');
            }
            if ($id = $request->route('id')) {
                return redirect("/admin/attendance/{$id}");
            }
        }

        return $next($request);
    }
}
