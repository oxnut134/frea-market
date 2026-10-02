<?php

namespace App\Http\Middleware;

use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureProfileExists
{
    /**
     * Send logged-in users without a profile to the first profile form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && ! Profile::where('user_id', Auth::id())->exists()) {
            return redirect()->route('profile.first');
        }

        return $next($request);
    }
}
