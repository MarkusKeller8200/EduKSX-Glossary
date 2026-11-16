<?php

namespace App\Http\Controllers;

use App\Access\AccessDenied;
use App\Access\AccountManager;
use App\Access\Audit;
use App\Access\RoleRequests;
use App\Access\Sso\MicrosoftLogin;
use App\Data\PersonalData;
use App\Enums\InstitutionKind;
use App\Glossary\TwoFactor;
use App\Models\OrganizationMember;
use App\Models\RoleRequest;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\History;
use App\Support\MailSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
                'institution_kind' => (string) $user->institution_kind,
                'is_unified_municipality' => $user->is_unified_municipality,
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
            'einrichtungen' => InstitutionKind::labels(),
            // Schularten mit dem Merkmal «Einheitsgemeinde» (#415)
            'schularten' => array_values(array_map(fn (InstitutionKind $k): string => $k->value, array_filter(InstitutionKind::cases(), fn (InstitutionKind $k): bool => $k->isSchool()))),
            'microsoft' => $microsoft->enabled(),
            // E-Mail-Adresse ändern nur mit Mailversand (#416)
            'emailAenderbar' => MailSetup::configured(),
            // Verlauf des eigenen Kontos (#417), unten aufklappbar
            'verlauf' => History::forUser($user),
            // Als Moderator/in bewerben (#460)
            'moderation' => $this->moderation($user),
            // ausgeblendete Hinweise wieder anzeigen (#469)
            'hinweise' => $this->notices($user),
        ]);
    }

    /** News-Einwilligung (#169): Antwort auf die Frage nach der Anmeldung bzw. Einstellung unter «Mein Konto». */
    /** @return array<string, mixed> */
    private function moderation(User $user): array
    {
        $requests = app(RoleRequests::class);
        $open = $requests->openFor($user);
        $last = RoleRequest::query()->where('user_id', $user->id)->where('status', RoleRequest::REJECTED)->latest('decided_at')->first();

        return [
            'moderator' => $requests->isModeratorOrMore($user),
            'fehlt' => $requests->missing($user),
            'offen' => $open !== null ? ['id' => $open->id, 'am' => $open->created_at?->toDateString(), 'begruendung' => $open->reason] : null,
            'abgelehnt' => $last !== null ? ['am' => $last->decided_at?->toDateString(), 'hinweis' => $last->decision_note] : null,
        ];
    }

    /**
     * Hinweise pro Konto (#469): ausgeblendet = true; null = gilt für dieses Konto nicht.
     *
     * @return array<string, bool|null>
     */
    private function notices(User $user): array
    {
        $domain = OrganizationMember::query()->where('user_id', $user->id)->where('joined_via', 'domain')->first();

        return [
            'moderation' => app(RoleRequests::class)->isModeratorOrMore($user) ? null : $user->moderator_notice_seen_at !== null,
            'news' => $user->news_answered_at !== null,
            'domain' => $domain === null ? null : $domain->notice_shown_at !== null,
            // Kenntnisnahme der Datenschutzerklärung: nur zum Testen, nur mit Recht «system»
            'datenschutz' => $user->hasPermission('system') ? $user->privacy_notice_version !== null : null,
        ];
    }

    /** Ausgeblendete Hinweise wieder anzeigen (#469): einzeln oder alle. */
    public function resetNotices(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $which = (string) $request->validate(['hinweis' => ['required', Rule::in(['moderation', 'news', 'domain', 'datenschutz', 'alle'])]])['hinweis'];
        $all = $which === 'alle';
        $reset = [];
        if ($all || $which === 'moderation') {
            $user->forceFill(['moderator_notice_seen_at' => null]);
            $reset[] = 'moderation';
        }
        if ($all || $which === 'news') {
            $user->forceFill(['news_answered_at' => null]);
            $reset[] = 'news';
        }
        if (($all || $which === 'datenschutz') && $user->hasPermission('system')) {
            $user->forceFill(['privacy_notice_version' => null, 'privacy_notice_acknowledged_at' => null]);
            $reset[] = 'datenschutz';
        }
        $user->save();
        if ($all || $which === 'domain') {
            OrganizationMember::query()->where('user_id', $user->id)->where('joined_via', 'domain')->update(['notice_shown_at' => null]);
            $reset[] = 'domain';
        }
        Audit::log($user, $user, 'account.notices_reset', ['hinweise' => implode(', ', $reset)]);

        return back()->with('status', $all ? 'Alle Hinweise erscheinen wieder.' : 'Der Hinweis erscheint wieder.');
    }

    /** Bewerbung als Moderator/in (#460). */
    public function applyModeration(Request $request): RedirectResponse
    {
        $data = $request->validate(['begruendung' => ['required', 'string', 'max:1000']]);
        try {
            app(RoleRequests::class)->apply($this->currentUser($request), (string) $data['begruendung']);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['begruendung' => $e->getMessage()]);
        }

        return back()->with('status', 'Danke! Ihre Bewerbung ist eingegangen; die Administration entscheidet und meldet sich per Mail.');
    }

    public function withdrawModeration(Request $request, RoleRequest $roleRequest): RedirectResponse
    {
        try {
            app(RoleRequests::class)->withdraw($this->currentUser($request), $roleRequest);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['begruendung' => $e->getMessage()]);
        }

        return back()->with('status', 'Bewerbung zurückgezogen.');
    }

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

    /** «Meine Daten» (#312, DB-Standard B8): alle Daten des eigenen Kontos; Aufruf im Sicherheitsprotokoll. */
    public function data(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        Audit::log($user, $user, 'account.data_exported');

        return self::download($user);
    }

    /** Antwort zum Herunterladen: alle Daten eines Kontos als JSON (#312, DB-Standard B8). */
    public static function download(User $user): JsonResponse
    {
        $data = app(PersonalData::class)->export($user);
        $name = 'glossary-meine-daten-'.now()->format('Y-m-d').'.json';

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_if($user->is_processing_restricted, 423, 'Die Bearbeitung dieses Kontos ist eingeschränkt.');
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'community_name' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[\pL\pN][\pL\pN ._-]*$/u',
                Rule::unique('users', 'community_name')->ignore($user->id)],
            'function' => ['nullable', 'string', 'max:100'],
            'institution_kind' => ['nullable', Rule::enum(InstitutionKind::class)],
            'is_unified_municipality' => ['boolean'],
            'institution_name' => ['nullable', 'string', 'max:150'],
        ], [
            'community_name.unique' => 'Dieser Community-Name ist schon vergeben.',
            'community_name.regex' => 'Nur Buchstaben, Ziffern, Leerzeichen, Punkt, Binde- und Unterstrich.',
        ]);
        foreach (['community_name', 'function', 'institution_kind', 'institution_name'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }
        // Einheitsgemeinde nur bei Schularten (#415)
        $data['is_unified_municipality'] = (bool) ($data['is_unified_municipality'] ?? false)
            && InstitutionKind::tryFrom((string) $data['institution_kind'])?->isSchool() === true;
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

    /**
     * Konto löschen: Passwort bestätigen; Schutzregeln (letzter Developer bzw. letzte Verwaltung) gelten. Ohne eigenes
     * Passwort (nur Anmeldedienst, #45): mit zweitem Faktor dessen Code, sonst ein Link an die eigene Adresse (#445).
     */
    public function destroy(Request $request, AccountManager $accounts, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->currentUser($request);
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);
        if ($user->has_password) {
            if (! Hash::check((string) $data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'Das Passwort stimmt nicht.']);
            }
        } elseif ($user->hasTwoFactor()) {
            $key = 'konto-loeschen|'.$user->id;
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages(['password' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($key).' Sekunden nochmals.']);
            }
            if (! $twoFactor->check($user, (string) $data['password'])) {
                RateLimiter::hit($key, 60);

                throw ValidationException::withMessages(['password' => 'Der Code stimmt nicht.']);
            }
        } else {
            if ($data['password'] !== 'LÖSCHEN') {
                throw ValidationException::withMessages(['password' => 'Bitte «LÖSCHEN» eingeben.']);
            }
            if (! MailSetup::configured()) {
                throw ValidationException::withMessages(['password' => 'Der Mailversand ist nicht eingerichtet – bitte an die Administration wenden.']);
            }
            $key = 'konto-loeschen-link|'.$user->id;
            if (RateLimiter::tooManyAttempts($key, 3)) {
                throw ValidationException::withMessages(['password' => 'Es wurden schon Links verschickt. Bitte in '.RateLimiter::availableIn($key).' Sekunden nochmals.']);
            }
            RateLimiter::hit($key, 3600);
            AccountDeletionController::sendLink($user);
            Audit::log($user, $user, 'account.delete_requested');

            return back()->with('status', 'Wir haben einen Link an Ihre E-Mail-Adresse geschickt. Erst damit wird das Konto gelöscht ('.AccountDeletionController::MINUTES.' Minuten).');
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
