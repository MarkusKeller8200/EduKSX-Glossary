<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration nur mit eingerichtetem zweitem Faktor (#11, KONZEPT-STACK): Ohne bestätigte 2FA führt jede Seite
 * der Administration zur Einrichtung unter «Mein Konto».
 */
final class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->hasTwoFactor()) {
            return redirect()->route('account.two-factor');
        }

        return $next($request);
    }
}
