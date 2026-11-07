<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MailSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Anmeldung der lokalen Konten (DEC-014): E-Mail und Passwort, höchstens 5 Versuche pro Minute je E-Mail und
 * Adresse; mit eingerichtetem zweitem Faktor folgt die Code-Abfrage (#11).
 */
class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/login', ['registrierung' => MailSetup::configured()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower((string) $credentials['email']);
        $key = $email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($key).' Sekunden nochmals.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null || ! Hash::check((string) $credentials['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['email' => 'E-Mail oder Passwort stimmt nicht.']);
        }
        RateLimiter::clear($key);
        if ($user->isPending()) {
            RegisterController::sendConfirmation($user);

            throw ValidationException::withMessages(['email' => 'Bitte zuerst die E-Mail-Adresse bestätigen – der Link ist nochmals unterwegs.']);
        }
        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => 'Anmeldung nicht möglich. Bitte an die Administration wenden.']);
        }

        // Zweiter Faktor eingerichtet → erst nach dem Code anmelden (#11)
        if ($user->hasTwoFactor()) {
            $request->session()->regenerate();
            $request->session()->put(['login.id' => $user->id, 'login.remember' => $request->boolean('remember')]);

            return redirect('/anmelden/code');
        }

        // Noch ohne zweiten Faktor: anmelden; wer privilegierte Rechte hat, muss ihn danach einrichten (#37)
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_on' => today()])->save();   // nur das Datum (Löschfristen, #39)

        return $user->needsTwoFactor() ? redirect()->intended('/admin') : redirect('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
