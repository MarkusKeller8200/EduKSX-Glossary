<?php

namespace App\Http\Controllers\Auth;

use App\Access\Audit;
use App\Http\Controllers\Controller;
use App\Mail\ConfirmRegistration;
use App\Models\User;
use App\Support\MailSetup;
use App\Support\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Selbstregistrierung mit Double-Opt-in (#41, docs/KONZEPT-BENUTZER.md 5.1): Spam-Schutz, Kenntnisnahme der
 * Datenschutzerklärung, Bestätigungslink 60 Minuten. Nur mit eingerichtetem Mailversand. Die Antwort verrät nicht,
 * ob eine Adresse schon ein Konto hat.
 */
class RegisterController extends Controller
{
    /** Gleiche Meldung für neue und bekannte Adressen. */
    private const SENT = 'Fast geschafft: Wir haben einen Bestätigungslink an die angegebene Adresse gesendet (60 Minuten gültig).';

    public function create(): Response
    {
        abort_unless(MailSetup::configured(), 404);

        return Inertia::render('auth/register', [
            'turnstile' => Turnstile::siteKey(),
            'datenschutzVersion' => (string) config('glossary.privacy_version'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(MailSetup::configured(), 404);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'datenschutz' => ['accepted'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:4000'],
        ], [
            'datenschutz.accepted' => 'Bitte die Kenntnisnahme der Datenschutzerklärung bestätigen.',
        ]);
        if (! Turnstile::verify($data['cf-turnstile-response'] ?? null, $request->ip())) {
            throw ValidationException::withMessages(['form' => 'Die Prüfung gegen Spam ist fehlgeschlagen. Bitte nochmals versuchen.']);
        }

        $email = strtolower((string) $data['email']);
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $user = new User([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $email,
                'password' => $data['password'],
            ]);
            $user->forceFill([
                'status' => 'pending',
                'privacy_notice_version' => (string) config('glossary.privacy_version'),
                'privacy_notice_acknowledged_at' => now(),
            ])->save();
            Audit::log($user, $user, 'account.registered');
            $this->sendConfirmation($user);
        } elseif ($user->isPending()) {
            $this->sendConfirmation($user);   // Link erneut senden, Angaben nicht überschreiben
        }
        // bestehendes aktives Konto: nichts verraten, nichts ändern

        return redirect('/anmelden')->with('status', self::SENT);
    }

    /** Bestätigungslink (signiert, 60 Minuten). */
    public function verify(Request $request, string $user, string $hash): RedirectResponse
    {
        $account = User::query()->find($user);
        if ($account === null || ! hash_equals(sha1($account->email), $hash)) {
            return redirect('/anmelden')->withErrors(['email' => 'Der Link ist ungültig. Bitte nochmals registrieren.']);
        }
        if ($account->isPending()) {
            $account->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
            Audit::log($account, $account, 'account.verified');
        }

        return redirect('/anmelden')->with('status', 'E-Mail-Adresse bestätigt. Sie können sich jetzt anmelden.');
    }

    /** Bestätigungslink senden; ein Ausfall des Mailversands blockiert die Registrierung nicht. */
    public static function sendConfirmation(User $user): void
    {
        $url = URL::temporarySignedRoute('register.verify', now()->addMinutes(60), [
            'user' => $user->id, 'hash' => sha1($user->email),
        ]);
        try {
            Mail::to($user->email)->send(new ConfirmRegistration($url));
        } catch (Throwable $e) {
            Log::warning('Bestätigungsmail fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }
}
