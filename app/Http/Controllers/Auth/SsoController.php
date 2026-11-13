<?php

namespace App\Http\Controllers\Auth;

use App\Access\Audit;
use App\Access\RoleManager;
use App\Access\Sso\MicrosoftLogin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserIdentity;
use App\Organizations\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Anmelden und Verknüpfen mit Microsoft (#45, docs/KONZEPT-BENUTZER.md 6.1 und 6.3).
 *
 * - Identität nur über Aussteller + Kennung (sub), nie über die E-Mail.
 * - Gleiche E-Mail eines bestehenden Kontos führt nie zur automatischen Verknüpfung: erst mit dem bisherigen Zugang
 *   anmelden und unter «Mein Konto» verknüpfen (Passwort bestätigen).
 * - Neue Konten erhalten die Rolle Benutzer (#110); externe Gruppen/Rollen zählen nicht.
 * - Mit eingerichtetem zweitem Faktor folgt die Code-Abfrage wie bei der Anmeldung mit Passwort.
 */
class SsoController extends Controller
{
    /** Wie lange nach der Passwortbestätigung eine Verknüpfung gestartet werden darf. */
    private const LINK_MINUTES = 10;

    public function __construct(
        private readonly MicrosoftLogin $microsoft,
        private readonly RoleManager $roles,
        private readonly OrganizationService $organizations,
    ) {}

    public function redirect(Request $request): SymfonyRedirect|RedirectResponse
    {
        abort_unless($this->microsoft->enabled(), 404);
        if ($request->user() !== null && ! $this->linkAllowed($request)) {
            return redirect('/konto');
        }

        return $this->microsoft->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->microsoft->enabled(), 404);
        $user = $request->user();
        $linking = $user instanceof User && $this->linkAllowed($request);
        $request->session()->forget('sso.link_until');
        if ($user !== null && ! $linking) {
            return redirect('/konto');   // schon angemeldet, aber keine bestätigte Verknüpfung
        }

        try {
            $claims = $this->microsoft->claims();
        } catch (Throwable $e) {
            Log::notice('Anmeldung mit Microsoft abgewiesen', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return redirect($linking ? '/konto' : '/anmelden')
                ->withErrors([$linking ? 'identitaet' : 'email' => 'Die Anmeldung mit Microsoft hat nicht geklappt. Bitte nochmals versuchen.']);
        }

        // Schlüssel ist issuer + subject (W8, #277); der Aussteller ist bei Microsoft je Tenant eigen
        $identity = UserIdentity::query()->where('issuer', $claims['issuer'])->where('subject', $claims['subject'])->first();

        if ($linking) {
            return $this->link($user, $identity, $claims);
        }

        return $identity !== null ? $this->login($request, $identity) : $this->register($request, $claims);
    }

    /** Verknüpfen vorbereiten: Passwort bestätigen, danach zu Microsoft. */
    public function startLink(Request $request): RedirectResponse
    {
        abort_unless($this->microsoft->enabled(), 404);
        $user = $this->currentUser($request);
        if (! $user->has_password) {
            throw ValidationException::withMessages(['identitaet' => 'Zum Verknüpfen zuerst ein eigenes Passwort setzen («Passwort vergessen»).']);
        }
        $this->confirm($request, $user, 'identitaet');
        $request->session()->put('sso.link_until', now()->addMinutes(self::LINK_MINUTES)->getTimestamp());

        return redirect()->route('sso.microsoft');
    }

    /** Verknüpfung lösen – nur mit Bestätigung und wenn eine andere Anmeldemethode bleibt. */
    public function unlink(Request $request, UserIdentity $identity): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_unless($identity->user_id === $user->id, 404);
        if (! $user->has_password) {
            throw ValidationException::withMessages(['identitaet' => 'Das ist die letzte Anmeldemethode. Zuerst ein Passwort setzen («Passwort vergessen»).']);
        }
        $this->confirm($request, $user, 'identitaet');
        $identity->delete();
        Audit::log($user, $user, 'identity.unlinked', ['provider' => $identity->provider]);

        return back()->with('status', 'Verknüpfung mit '.(UserIdentity::PROVIDERS[$identity->provider] ?? $identity->provider).' gelöst.');
    }

    // ---------- intern ----------

    /** @param  array{issuer: string, subject: string, tenant_id: string|null, email: string|null, given_name: string|null, family_name: string|null, name: string|null}  $claims */
    private function link(User $user, ?UserIdentity $identity, array $claims): RedirectResponse
    {
        if ($identity !== null && $identity->user_id !== $user->id) {
            Audit::log($user, $user, 'identity.linked', ['provider' => 'microsoft', 'reason' => 'gehört zu einem anderen Konto'], 'denied');

            return redirect('/konto')->withErrors(['identitaet' => 'Dieses Microsoft-Konto ist schon mit einem anderen Konto verknüpft.']);
        }
        if ($identity === null) {
            $user->identities()->create($this->identityData($claims) + ['last_used_at' => now()]);
            Audit::log($user, $user, 'identity.linked', ['provider' => 'microsoft']);
        }

        return redirect('/konto')->with('status', 'Microsoft-Konto verknüpft.');
    }

    private function login(Request $request, UserIdentity $identity): RedirectResponse
    {
        $user = $identity->user;
        if (! $user->isActive()) {
            return redirect('/anmelden')->withErrors(['email' => 'Anmeldung nicht möglich. Bitte an die Administration wenden.']);
        }
        $identity->forceFill(['last_used_at' => now()])->save();
        $this->organizations->inviteByTenant($user, $identity->tenant_id);

        // zweiter Faktor wie bei der Anmeldung mit Passwort (#37)
        if ($user->hasTwoFactor()) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->id, 'login.remember' => false]);

            return redirect('/anmelden/code');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_on' => today(), 'inactivity_notice_sent_at' => null])->save();

        return $user->needsTwoFactor() ? redirect()->intended('/admin') : redirect('/');
    }

    /** @param  array{issuer: string, subject: string, tenant_id: string|null, email: string|null, given_name: string|null, family_name: string|null, name: string|null}  $claims */
    private function register(Request $request, array $claims): RedirectResponse
    {
        $email = $claims['email'] !== null ? Str::lower($claims['email']) : null;
        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect('/anmelden')->withErrors(['email' => 'Microsoft hat keine E-Mail-Adresse geliefert. Bitte ein Konto mit E-Mail und Passwort anlegen.']);
        }
        // gleiche E-Mail ≠ gleiche Person: nie automatisch verknüpfen (Abnahmetest 4)
        if (User::query()->where('email', $email)->exists()) {
            return redirect('/anmelden')->withErrors(['email' => 'Für diese E-Mail gibt es schon ein Konto. Bitte zuerst wie gewohnt anmelden und unter «Mein Konto» Microsoft verknüpfen.']);
        }

        [$first, $last] = $this->names($claims);
        $user = DB::transaction(function () use ($claims, $email, $first, $last): User {
            $user = new User(['first_name' => $first, 'last_name' => $last, 'email' => $email]);
            // kein eigenes Passwort: zufälliger, unbekannter Wert; setzen über «Passwort vergessen»
            $user->forceFill(['password' => Str::password(48), 'has_password' => false, 'status' => 'active'])->save();
            $user->identities()->create($this->identityData($claims) + ['last_used_at' => now()]);
            Audit::log($user, $user, 'account.registered', ['provider' => 'microsoft']);
            $this->roles->assignDefault($user, 'Registrierung (Microsoft)');
            $this->organizations->inviteByTenant($user, $claims['tenant_id']);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_on' => today()])->save();

        // Datenschutz-Kenntnisnahme fragt die Middleware beim nächsten Aufruf ab (#42)
        return redirect('/konto')->with('status', 'Willkommen! Bitte die Angaben prüfen.');
    }

    /**
     * @param  array{issuer: string, subject: string, tenant_id: string|null, email: string|null, given_name: string|null, family_name: string|null, name: string|null}  $claims
     * @return array{provider: string, issuer: string, subject: string, tenant_id: string|null}
     */
    private function identityData(array $claims): array
    {
        return [
            'provider' => 'microsoft',
            'issuer' => $claims['issuer'],
            'subject' => $claims['subject'],
            'tenant_id' => $claims['tenant_id'],
        ];
    }

    /**
     * @param  array{given_name: string|null, family_name: string|null, name: string|null}  $claims
     * @return array{0: string, 1: string}
     */
    private function names(array $claims): array
    {
        if ($claims['given_name'] !== null || $claims['family_name'] !== null) {
            return [mb_substr((string) $claims['given_name'], 0, 80), mb_substr((string) $claims['family_name'], 0, 80)];
        }
        $parts = preg_split('/\s+/', trim((string) $claims['name'])) ?: [];
        $last = count($parts) > 1 ? (string) array_pop($parts) : '';

        return [mb_substr(implode(' ', $parts), 0, 80), mb_substr($last, 0, 80)];
    }

    private function linkAllowed(Request $request): bool
    {
        return (int) $request->session()->get('sso.link_until', 0) >= now()->getTimestamp();
    }

    /** Erneute Anmeldung mit der bisherigen, vertrauenswürdigen Methode: dem eigenen Passwort. */
    private function confirm(Request $request, User $user, string $field): void
    {
        if (! $user->has_password || ! Hash::check((string) $request->input('password', ''), $user->password)) {
            throw ValidationException::withMessages([$field => 'Das Passwort stimmt nicht.']);
        }
    }
}
