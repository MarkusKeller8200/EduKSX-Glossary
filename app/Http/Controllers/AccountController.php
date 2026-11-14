<?php

namespace App\Http\Controllers;

use App\Access\AccessDenied;
use App\Access\AccountManager;
use App\Access\Audit;
use App\Access\Sso\MicrosoftLogin;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mein Konto» (#39, docs/KONZEPT-BENUTZER.md 4 und 5.4): Angaben, Passwort, zweiter Faktor, Konto löschen. Für
 * alle angemeldeten Konten, auch Community. Die E-Mail-Adresse ändert sich erst mit Bestätigung per Mail (#41).
 */
class AccountController extends Controller
{
    public function show(Request $request, MicrosoftLogin $microsoft): Response
    {
        $user = $this->currentUser($request);

        return Inertia::render('account/show', [
            'konto' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'community_name' => (string) $user->community_name,
                'function' => (string) $user->function,
                'institution_type' => (string) $user->institution_type,
                'institution_name' => (string) $user->institution_name,
                'zweiter_faktor' => $user->hasTwoFactor(),
                'zweiter_faktor_pflicht' => $user->needsTwoFactor(),
                'passwort_gesetzt' => $user->has_password,
                'identitaeten' => $user->identities()->orderBy('created_at')->get()->map(fn (UserIdentity $i): array => [
                    'id' => $i->id,
                    'anbieter' => UserIdentity::PROVIDERS[$i->provider] ?? $i->provider,
                    'art' => $i->entra_tenant_id === UserIdentity::MICROSOFT_CONSUMER_TENANT ? 'privates Konto' : 'Geschäfts- oder Schulkonto',
                    'seit' => $i->created_at?->toDateString(),
                    'zuletzt' => $i->last_used_at?->toDateString(),
                ])->all(),
                'rollen' => $user->roleKeys(),
                'news' => $user->has_news_opt_in,
                'news_seit' => $user->news_answered_at?->toDateString(),
            ],
            'einrichtungen' => User::INSTITUTION_TYPES,
            'microsoft' => $microsoft->enabled(),
        ]);
    }

    /** News-Einwilligung (#169): Antwort auf die Frage nach der Anmeldung bzw. Einstellung unter «Mein Konto». */
    public function news(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $optIn = (bool) $request->validate(['news' => ['required', 'boolean']])['news'];
        $user->answerNews($optIn);
        Audit::log($user, $user, 'news.answered', ['news' => $optIn]);

        return back()->with('status', $optIn
            ? 'Danke – Sie erhalten News zum Glossary (keine Werbung). Abmelden jederzeit unter «Mein Konto».'
            : 'Sie erhalten keine News. Ändern jederzeit unter «Mein Konto».');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'community_name' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[\pL\pN][\pL\pN ._-]*$/u',
                Rule::unique('users', 'community_name')->ignore($user->id)],
            'function' => ['nullable', 'string', 'max:100'],
            'institution_type' => ['nullable', Rule::in(array_keys(User::INSTITUTION_TYPES))],
            'institution_name' => ['nullable', 'string', 'max:150'],
        ], [
            'community_name.unique' => 'Dieser Community-Name ist schon vergeben.',
            'community_name.regex' => 'Nur Buchstaben, Ziffern, Leerzeichen, Punkt, Binde- und Unterstrich.',
        ]);
        foreach (['community_name', 'function', 'institution_type', 'institution_name'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }
        $user->fill($data)->save();

        return back()->with('status', 'Angaben gespeichert.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        if (! Hash::check((string) $data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Das bisherige Passwort stimmt nicht.']);
        }
        $user->forceFill(['password' => $data['password'], 'remember_token' => null])->save();
        // andere Sitzungen beenden (Datenbank-Sitzungen), die eigene erneuern
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())->delete();
        }
        $request->session()->regenerate();
        Audit::log($user, $user, 'account.password');

        return back()->with('status', 'Passwort geändert. Andere Sitzungen wurden abgemeldet.');
    }

    /** Konto löschen: Passwort bestätigen; Schutzregeln (letzter Developer bzw. letzte Verwaltung) gelten. */
    public function destroy(Request $request, AccountManager $accounts): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate(['password' => ['required', 'string']]);
        // ohne eigenes Passwort (nur Anmeldedienst, #45): Eingabe «LÖSCHEN» als Bestätigung
        $ok = $user->has_password ? Hash::check((string) $data['password'], $user->password) : $data['password'] === 'LÖSCHEN';
        if (! $ok) {
            throw ValidationException::withMessages(['password' => $user->has_password ? 'Das Passwort stimmt nicht.' : 'Bitte «LÖSCHEN» eingeben.']);
        }
        try {
            $accounts->delete($user, $user);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Konto gelöscht.');
    }
}
