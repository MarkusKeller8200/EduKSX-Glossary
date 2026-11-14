<?php

namespace App\Http\Middleware;

use App\Access\Service\ServiceAccountManager;
use Closure;
use EduKSX\Core\Http\ProblemException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schreibende API nur mit Token eines Servicekontos (#47): «Authorization: Bearer gls_…», optional mit Scope
 * (`service.token:propose`). Keine Sitzung, kein Cookie; das Token steht danach unter dem Attribut «service_token».
 */
class AuthenticateServiceToken
{
    public function __construct(private readonly ServiceAccountManager $manager) {}

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = $this->manager->authenticate($request->bearerToken(), $scope);
        if ($token === null) {
            // Fehlerformat nach API-Standard A6 (#320)
            throw ProblemException::common(401, 'unauthenticated', 'Gültiges Token eines Servicekontos nötig.', [
                'WWW-Authenticate' => 'Bearer realm="glossary"',
                'Cache-Control' => 'no-store',
            ]);
        }
        $request->attributes->set('service_token', $token);

        return $next($request);
    }
}
