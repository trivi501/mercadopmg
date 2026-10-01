<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea a usuarios desactivados: cierra la sesión web o revoca el token API.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            if ($request->is('api/*')) {
                // Un usuario desactivado pierde todas sus sesiones móviles.
                $user->tokens()->delete();

                return response()->json(['message' => 'Usuario desactivado.', 'code' => 'user_inactive'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Su usuario está desactivado.']);
        }

        return $next($request);
    }
}
