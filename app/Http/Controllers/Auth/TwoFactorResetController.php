<?php

namespace App\Http\Controllers\Auth;

use App\Access\AccountManager;
use App\Access\Audit;
use App\Glossary\TwoFactor;
use App\Http\Controllers\Controller;
use App\Mail\TwoFactorRemoved;
use App\Mail\TwoFactorResetLink;
use App\Models\User;
use App\Support\MailSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Zweiten Faktor selbst zurücksetzen (#403): Im Schritt «Code» schickt «Zweiten Faktor zurücksetzen» einen Link an die
 * Adresse des Kontos (60 Minuten, signiert, nur einmal: er hängt am heutigen Geheimnis). Der Link verlangt E-Mail und
 * Passwort; danach ist der zweite Faktor gelöscht, alle Sitzungen enden, das Konto erhält eine Mail, und nach der
 * nächsten Anmeldung wird das Einrichten angeboten.
 *
 * Nur für Konten ohne erhöhte Rechte (Entscheid 15.11.2026): wer den zweiten Faktor für Moderation, Administration oder
 * als Organisations-Admin braucht, lässt ihn über die Administration bzw. den Organisations-Admin zurücksetzen.
 */
class TwoFactorResetController extends Controller
{
    private const MINUTES = 60;

    public function __construct(private readonly TwoFactor $twoFactor, private readonly AccountManager $accounts) {}

    /** Link anfordern – nur im Schritt «Code» der Anmeldung, also nach richtigem Passwort. */
    public function request(Request $request): RedirectResponse
    {
        $id = $request->session()->get('login.id');
        $user = is_string($id) ? User::query()->find($id) : null;
        if ($user === null || ! $user->isActive() || ! $user->hasTwoFactor()) {
            return redirect('/anmelden');
        }
        if ($user->needsTwoFactor()) {
            Audit::log($user, $user, 'mfa.reset_requested', ['reason' => 'erhöhte Rechte'], 'denied');

            throw ValidationException::withMessages(['code' => 'Für dieses Konto setzt die Administration den zweiten Faktor zurück (erhöhte Rechte).']);
        }
        if (! MailSetup::configured()) {
            throw ValidationException::withMessages(['code' => 'Der Mailversand ist nicht eingerichtet – bitte an die Administration wenden.']);
        }
        $key = '2fa-reset|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['code' => 'Es wurden schon Links verschickt. Bitte in '.RateLimiter::availableIn($key).' Sekunden nochmals.']);
        }
        RateLimiter::hit($key, 3600);

        $url = URL::temporarySignedRoute('two-factor.reset', now()->addMinutes(self::MINUTES), [
            'user' => $user->id,
            'hash' => self::fingerprint($user),
        ]);
        try {
            Mail::to($user->email)->send(new TwoFactorResetLink($url, self::MINUTES));
        } catch (Throwable $e) {
            Log::warning('Mail «Zweiten Faktor zurücksetzen» fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
        Audit::log($user, $user, 'mfa.reset_requested');

        return back()->with('status', 'Wir haben einen Link an Ihre E-Mail-Adresse geschickt. Er gilt '.self::MINUTES.' Minuten.');
    }

    /** Seite des Links: E-Mail und Passwort bestätigen. */
    public function show(Request $request, User $user, string $hash): Response|RedirectResponse
    {
        if (! $this->valid($user, $hash)) {
            return redirect('/anmelden')->with('status', 'Der Link ist abgelaufen oder wurde schon verwendet.');
        }

        return Inertia::render('auth/two-factor-reset', ['aktion' => $request->fullUrl()]);
    }

    public function reset(Request $request, User $user, string $hash): RedirectResponse
    {
        if (! $this->valid($user, $hash)) {
            return redirect('/anmelden')->with('status', 'Der Link ist abgelaufen oder wurde schon verwendet.');
        }
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = '2fa-reset-pw|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($key).' Sekunden nochmals.']);
        }
        if (mb_strtolower((string) $data['email']) !== mb_strtolower($user->email) || ! $user->has_password
            || ! Hash::check((string) $data['password'], (string) $user->password)) {
            RateLimiter::hit($key, 60);
            Audit::log($user, $user, 'mfa.reset', ['durch' => 'Link per Mail'], 'denied');

            throw ValidationException::withMessages(['email' => 'E-Mail oder Passwort stimmen nicht.']);
        }
        RateLimiter::clear($key);

        $this->twoFactor->reset($user);
        $this->accounts->endSessions($user);
        Audit::log($user, $user, 'mfa.reset', ['durch' => 'Link per Mail']);
        if (MailSetup::configured()) {
            Mail::to($user->email)->send(new TwoFactorRemoved('über den Link per Mail'));
        }
        // Nach der nächsten Anmeldung das Einrichten anbieten (#403)
        $request->session()->forget('login.id');
        $request->session()->put('url.intended', url('/konto/zwei-faktor'));

        return redirect('/anmelden')->with('status', 'Zweiter Faktor entfernt. Bitte neu anmelden – danach können Sie ihn wieder einrichten.');
    }

    /** Link gilt nur, solange das Konto aktiv ist, keine erhöhten Rechte hat und den gleichen zweiten Faktor hat. */
    private function valid(User $user, string $hash): bool
    {
        return $user->isActive() && $user->hasTwoFactor() && ! $user->needsTwoFactor()
            && hash_equals(self::fingerprint($user), $hash);
    }

    private static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', $user->id.'|'.$user->two_factor_secret, (string) config('app.key')), 0, 40);
    }
}
