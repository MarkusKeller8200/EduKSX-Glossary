<?php

namespace App\Access\Sso;

use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use SocialiteProviders\Microsoft\Provider;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Anmeldung mit Microsoft über OpenID Connect (#45, docs/KONZEPT-BENUTZER.md 6.1): Authorization Code Flow mit PKCE
 * und state (Socialite), id_token mit Signatur, Aussteller, Audience und Ablauf geprüft (SocialiteProviders). Liefert
 * nur die für die Identität nötigen Angaben; externe Gruppen und Rollen werden nicht übernommen.
 */
class MicrosoftLogin
{
    /** Nur die nötigen Angaben; User.Read verlangt der Provider selbst. */
    private const SCOPES = ['openid', 'profile', 'email'];

    public function enabled(): bool
    {
        return (string) config('services.microsoft.client_id') !== '' && (string) config('services.microsoft.client_secret') !== '';
    }

    public function redirect(): RedirectResponse
    {
        return $this->driver()->enablePKCE()->scopes(self::SCOPES)->redirect();
    }

    /**
     * Rückkehr von Microsoft auswerten.
     *
     * @return array{issuer: string, subject: string, tenant_id: string|null, object_id: string|null, email: string|null, given_name: string|null, family_name: string|null, name: string|null}
     *
     * @throws RuntimeException wenn die Anmeldung nicht gültig ist
     */
    public function claims(): array
    {
        $driver = $this->driver()->enablePKCE()->scopes(self::SCOPES);
        $driver->user();   // tauscht den Code, prüft state und PKCE
        $claims = $driver->getClaims();   // id_token: Signatur, iss, aud, exp geprüft
        if ($claims === null || ! isset($claims->iss, $claims->sub)) {
            throw new RuntimeException('Microsoft hat kein gültiges id_token geliefert.');
        }
        $str = fn (string $key): ?string => isset($claims->{$key}) && is_scalar($claims->{$key}) && (string) $claims->{$key} !== ''
            ? (string) $claims->{$key} : null;

        return [
            'issuer' => (string) $claims->iss,
            'subject' => (string) $claims->sub,
            'tenant_id' => $str('tid'),
            'object_id' => $str('oid'),
            'email' => $str('email') ?? $str('preferred_username'),
            'given_name' => $str('given_name'),
            'family_name' => $str('family_name'),
            'name' => $str('name'),
        ];
    }

    private function driver(): Provider
    {
        $driver = Socialite::driver('microsoft');
        if (! $driver instanceof Provider) {
            throw new RuntimeException('Socialite-Provider für Microsoft ist nicht registriert.');
        }

        return $driver;
    }
}
