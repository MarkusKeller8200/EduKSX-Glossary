<?php

namespace App\Access\Sso;

use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use SocialiteProviders\Microsoft\Provider;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Anmeldung mit Microsoft über OpenID Connect (#45, docs/KONZEPT-BENUTZER.md 6.1): Authorization Code Flow mit PKCE
 * und state (Socialite), id_token mit Signatur, Aussteller, Audience und Ablauf geprüft (SocialiteProviders), dazu ein
 * einmaliger nonce je Anmeldung (#61), in der Sitzung gespeichert und im id_token verglichen. Liefert
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

    /** Sitzungsschlüssel des nonce (#61). */
    public const NONCE_KEY = 'sso.microsoft.nonce';

    public function redirect(): RedirectResponse
    {
        $nonce = Str::random(40);
        session()->put(self::NONCE_KEY, $nonce);

        return $this->driver()->enablePKCE()->scopes(self::SCOPES)->with(['nonce' => $nonce])->redirect();
    }

    /**
     * nonce im id_token mit dem der Sitzung vergleichen (einmal verwendbar).
     *
     * @throws RuntimeException bei fehlendem oder falschem nonce
     */
    public static function checkNonce(object $claims, ?string $expected): void
    {
        $nonce = isset($claims->nonce) && is_string($claims->nonce) ? $claims->nonce : '';
        if ($expected === null || $expected === '' || $nonce === '' || ! hash_equals($expected, $nonce)) {
            throw new RuntimeException('Anmeldung mit Microsoft abgelehnt: nonce fehlt oder stimmt nicht.');
        }
    }

    /**
     * Rückkehr von Microsoft auswerten.
     *
     * @return array{issuer: string, subject: string, tenant_id: string|null, email: string|null, given_name: string|null, family_name: string|null, name: string|null}
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
        $expected = session()->pull(self::NONCE_KEY);
        self::checkNonce($claims, is_string($expected) ? $expected : null);
        $str = fn (string $key): ?string => isset($claims->{$key}) && is_scalar($claims->{$key}) && (string) $claims->{$key} !== ''
            ? (string) $claims->{$key} : null;

        return [
            'issuer' => (string) $claims->iss,
            'subject' => (string) $claims->sub,
            'tenant_id' => $str('tid'),
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
