<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->admin_status) {
            return redirect('/attendance/list');
        }

        return $next($request);
    }
}
