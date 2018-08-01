<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Services\SamlAuth;

class RedirectIfAuthenticated
{
    use SamlAuth;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param string|null              $guard
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            if ($request->filled('SAMLRequest')) {
                $this->handleSamlLoginRequest($request);

                return redirect()->route('saml.auth');
            }

            return redirect('/home');
        }

        return $next($request);
    }
}
