<?php

namespace App\Http\Controllers\Auth;

use App\Access\Audit;
use App\Glossary\TwoFactor;
use App\Http\Controllers\Controller;
use App\Mail\RecoveryCodesRenewed;
use App\Models\User;
use App\Support\MailSetup;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zweiter Faktor (#11): Code bei der Anmeldung abfragen, einrichten (Schlüssel + erster Code), neue
 * Wiederherstellungscodes. Ohne Zusatzpaket (App\Support\Totp).
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    /** Schritt 2 der Anmeldung: Code eingeben (Passwort war richtig). */
    public function challenge(Request $request): Response|RedirectResponse
    {
        $id = $request->session()->get('login.id');
        $user = is_string($id) ? User::query()->find($id) : null;
        if ($user === null) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect('/anmelden');
        }

        // Konto anzeigen, mit dem man sich gerade anmeldet (#440); das Passwort war bereits richtig
        return Inertia::render('auth/two-factor', ['konto' => $user->email]);
    }

    /** Schritt 2 abbrechen und mit einem anderen Konto anmelden (#440). */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['login.id', 'login.remember']);

        return redirect('/anmelden');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $id = $request->session()->get('login.id');
        $user = is_string($id) ? User::query()->find($id) : null;
        if ($user === null || ! $user->isActive()) {
            $request->session()->forget('login.id');

            return redirect('/anmelden');
        }

        $key = '2fa|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($key).' Sekunden nochmals.']);
        }
        if (! $this->twoFactor->check($user, (string) $data['code'])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'Der Code stimmt nicht.']);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_on' => today(), 'inactivity_notice_sent_at' => null])->save();   // nur das Datum (Löschfristen, #39, #42)

        return $user->needsTwoFactor() ? redirect()->intended('/admin') : redirect('/');
    }

    /** Einrichtung (Pflicht vor der Administration) bzw. Übersicht, wenn schon eingerichtet. */
    public function setup(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $secret = null;
        if (! $user->hasTwoFactor()) {
            $secret = $user->two_factor_secret ?? $this->twoFactor->start($user);
        }

        return Inertia::render('account/two-factor', [
            'pflicht' => $user->needsTwoFactor(),
            'eingerichtet' => $user->hasTwoFactor(),
            'schluessel' => $secret !== null ? trim(chunk_split($secret, 4, ' ')) : null,
            'uri' => $secret !== null ? Totp::uri($secret, $user->email) : null,
            'codes' => $request->session()->get('recovery_codes'),
            'verbleibend' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        /** @var User $user */
        $user = $request->user();
        $codes = $this->twoFactor->confirm($user, (string) $data['code']);
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'Der Code stimmt nicht. Uhrzeit des Handys prüfen und den aktuellen Code eingeben.']);
        }

        Audit::log($user, $user, 'mfa.enabled');

        return redirect()->route('account.two-factor')
            ->with('recovery_codes', $codes)
            ->with('status', 'Zweiter Faktor eingerichtet. Wiederherstellungscodes jetzt sicher aufbewahren.');
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        // nur mit aktuellem Code aus der App oder gültigem Wiederherstellungscode (#446, Security-Review SEC-02)
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $key = '2fa-codes|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($key).' Sekunden nochmals.']);
        }
        if (! $this->twoFactor->check($user, (string) $data['code'])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'Der Code stimmt nicht.']);
        }
        RateLimiter::clear($key);

        $codes = $this->twoFactor->newRecoveryCodes($user);
        Audit::log($user, $user, 'mfa.recovery_codes');
        if (MailSetup::configured()) {
            Mail::to($user->email)->send(new RecoveryCodesRenewed);
        }

        return redirect()->route('account.two-factor')
            ->with('recovery_codes', $codes)
            ->with('status', 'Neue Wiederherstellungscodes erzeugt – die alten gelten nicht mehr.');
    }
}
