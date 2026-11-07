<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration nur mit eingerichtetem zweitem Faktor (#11, KONZEPT-STACK): Ohne bestätigte 2FA führt jede Seite
 * der Administration zur Einrichtung.
 */
final class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->hasTwoFactor() && ! $request->routeIs('admin.two-factor*')) {
            return redirect()->route('admin.two-factor');
        }

        return $next($request);
    }
}
