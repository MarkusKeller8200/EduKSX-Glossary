<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gesperrte Konten verlieren sofort den Zugang (#37), auch mit noch offener Sitzung oder «angemeldet bleiben».
 */
final class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/anmelden')->withErrors(['email' => 'Anmeldung nicht möglich. Bitte an die Administration wenden.']);
        }

        return $next($request);
    }
}
