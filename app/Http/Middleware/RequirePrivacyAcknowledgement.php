<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Angemeldete Konten nehmen die geltende Version der Datenschutzerklärung zur Kenntnis (#42, DEC-019). Bei einer neuen
 * Version (GLOSSARY_PRIVACY_VERSION) führt die nächste Seite einmal zur Kenntnisnahme. Ohne Version: aus.
 */
final class RequirePrivacyAcknowledgement
{
    /** Diese Routen bleiben erreichbar (Lesen, Abmelden, Konto löschen). */
    private const EXCEPT = ['privacy', 'privacy.*', 'logout', 'account.destroy', 'legals'];

    public function handle(Request $request, Closure $next): Response
    {
        $version = (string) config('glossary.privacy_version');
        $user = $request->user();
        if ($version !== '' && $user instanceof User && $user->privacy_notice_version !== $version
            && ! $request->routeIs(...self::EXCEPT) && $request->isMethod('GET')) {
            return redirect()->guest(route('privacy.acknowledge'));
        }

        return $next($request);
    }
}
