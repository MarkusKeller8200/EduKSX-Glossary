<?php

namespace App\Http\Controllers\Auth;

use App\Access\AccountManager;
use App\Access\Audit;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MailSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Passwort vergessen (#41, docs/KONZEPT-BENUTZER.md 5.2): einmaliger Link per E-Mail (60 Minuten); die Antwort verrät
 * nicht, ob ein Konto existiert. Nach dem Zurücksetzen enden alle Sitzungen.
 */
class PasswordResetController extends Controller
{
    private const SENT = 'Falls ein Konto mit dieser Adresse besteht, ist ein Link zum Zurücksetzen unterwegs (60 Minuten gültig).';

    public function create(): Response
    {
        abort_unless(MailSetup::configured(), 404);

        return Inertia::render('auth/forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(MailSetup::configured(), 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = strtolower((string) $data['email']);

        $user = User::query()->where('email', $email)->first();
        if ($user !== null && $user->isActive()) {
            try {
                Password::sendResetLink(['email' => $email]);
            } catch (Throwable $e) {
                Log::warning('Mail «Passwort zurücksetzen» fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
            }
        }

        return back()->with('status', self::SENT);
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request, AccountManager $accounts): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => strtolower((string) $data['email']), 'token' => $data['token'], 'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation')],
            function (User $user, string $password) use ($accounts): void {
                $user->forceFill(['password' => $password, 'password_set' => true])->save();
                $accounts->endSessions($user);
                Audit::log($user, $user, 'account.password_reset');
            },
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Der Link ist ungültig oder abgelaufen. Bitte einen neuen anfordern.']);
        }

        return redirect('/anmelden')->with('status', 'Passwort gesetzt. Sie können sich jetzt anmelden.');
    }
}
