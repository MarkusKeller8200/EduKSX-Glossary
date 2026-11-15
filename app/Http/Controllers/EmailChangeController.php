<?php

namespace App\Http\Controllers;

use App\Access\Audit;
use App\Mail\ConfirmEmailChange;
use App\Mail\EmailChangeNotice;
use App\Models\User;
use App\Support\MailSetup;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Eigene E-Mail-Adresse ändern mit Bestätigung (#416, Double-Opt-in): Der Link geht an die **neue** Adresse (signiert,
 * 60 Minuten), die alte Adresse erhält einen Hinweis. Erst der Klick ändert die Adresse. Der Link trägt die neue Adresse
 * verschlüsselt und hängt an der bisherigen Adresse – nach der Änderung ist er verbraucht. Ist die neue Adresse schon
 * vergeben, lautet die Antwort gleich (nichts verraten), es geht aber kein Link hinaus.
 */
class EmailChangeController extends Controller
{
    public const SENT = 'Wir haben einen Link an die neue Adresse geschickt. Erst nach der Bestätigung gilt sie (60 Minuten).';

    public function request(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        abort_if($user->is_processing_restricted, 423, 'Die Bearbeitung dieses Kontos ist eingeschränkt.');
        if (! MailSetup::configured()) {
            throw ValidationException::withMessages(['email' => 'Der Mailversand ist nicht eingerichtet – die Adresse lässt sich noch nicht ändern.']);
        }
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'current_password' => [$user->has_password ? 'required' : 'nullable', 'string'],
        ]);
        if ($user->has_password && ! Hash::check((string) $data['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Das Passwort stimmt nicht.']);
        }
        $new = mb_strtolower(trim((string) $data['email']));
        if ($new === mb_strtolower($user->email)) {
            throw ValidationException::withMessages(['email' => 'Das ist schon die Adresse dieses Kontos.']);
        }

        Audit::log($user, $user, 'account.email_change_requested');
        if (User::query()->whereRaw('lower(email) = ?', [$new])->exists()) {
            return back()->with('status', self::SENT);   // vergeben: gleiche Antwort, kein Link
        }
        $url = URL::temporarySignedRoute('account.email.confirm', now()->addMinutes(60), [
            'user' => $user->id,
            'hash' => self::fingerprint($user),
            'neu' => Crypt::encryptString($new),
        ]);
        try {
            Mail::to($new)->send(new ConfirmEmailChange($url));
            Mail::to($user->email)->send(new EmailChangeNotice(self::mask($new)));
        } catch (Throwable $e) {
            Log::warning('Mail «E-Mail ändern» fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }

        return back()->with('status', self::SENT);
    }

    /** Bestätigungslink aus der Mail an die neue Adresse (signiert). */
    public function confirm(Request $request, User $user, string $hash): RedirectResponse
    {
        $target = $request->user() !== null ? '/konto' : '/anmelden';
        try {
            $new = Crypt::decryptString($request->string('neu')->value());
        } catch (DecryptException) {
            $new = '';
        }
        if ($new === '' || ! $user->isActive() || ! hash_equals(self::fingerprint($user), $hash)) {
            return redirect($target)->with('status', 'Der Link ist abgelaufen oder wurde schon verwendet.');
        }
        if (User::query()->whereRaw('lower(email) = ?', [$new])->exists()) {
            return redirect($target)->with('status', 'Diese Adresse wird schon für ein anderes Konto verwendet.');
        }
        $old = $user->email;
        $user->forceFill(['email' => $new, 'email_verified_at' => now()])->save();
        Audit::log($user, $user, 'account.email_changed', ['alt' => $old, 'neu' => $new]);

        return redirect($target)->with('status', 'E-Mail-Adresse geändert. Bitte künftig mit der neuen Adresse anmelden.');
    }

    private static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', $user->id.'|'.mb_strtolower($user->email), (string) config('app.key')), 0, 40);
    }

    /** a***@example.org – in der Mail an die alte Adresse */
    private static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
