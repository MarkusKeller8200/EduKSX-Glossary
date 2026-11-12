<?php

namespace App\Http\Controllers\Admin;

use App\Access\Audit;
use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\User;
use App\Support\AppVersion;
use App\Support\ConfigCheck;
use App\Support\MailSettings;
use App\Support\MailSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Systemseite (#40, Recht «system»): Stand des E-Mail-Versands und Testmail; keine Zugangsdaten anzeigen. */
class SystemController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/system', [
            'mail' => MailSetup::info(),
            'warteschlange' => [
                'offen' => DB::table('jobs')->count(),
                'fehlgeschlagen' => DB::table('failed_jobs')->count(),
            ],
            'version' => AppVersion::info()['version'],
            // Prüfung der Konfiguration (#196), ohne Werte von Geheimnissen
            'pruefung' => ConfigCheck::run(),
            // Mail-Versand in der Administration (#219), ohne Passwort
            'mailEinstellung' => MailSettings::form(),
        ]);
    }

    /** Mail-Versand speichern und sofort mit einer Testmail prüfen (#219); gesperrt, wenn die Umgebung ihn vorgibt. */
    public function saveMail(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        if (MailSettings::fromEnvironment()) {
            throw ValidationException::withMessages(['mailer' => 'Der Versand ist auf dem Server über die Umgebung (MAIL_MAILER) eingestellt und hier gesperrt.']);
        }
        $data = $request->validate([
            'mailer' => ['required', Rule::in(MailSettings::MAILERS)],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:190', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port' => ['nullable', 'required_if:mailer,smtp', 'integer', 'between:1,65535'],
            'scheme' => ['nullable', Rule::in(['', 'smtp', 'smtps'])],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:500'],
            'passwort_loeschen' => ['boolean'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'an' => ['required', 'email', 'max:190'],
        ]);

        MailSettings::save($user, [
            'mailer' => (string) $data['mailer'],
            'host' => isset($data['host']) ? strtolower((string) $data['host']) : null,
            'port' => isset($data['port']) ? (int) $data['port'] : null,
            'scheme' => isset($data['scheme']) ? (string) $data['scheme'] : null,
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'password' => isset($data['password']) ? (string) $data['password'] : null,
            'from_address' => (string) $data['from_address'],
            'from_name' => isset($data['from_name']) ? (string) $data['from_name'] : null,
        ], (bool) ($data['passwort_loeschen'] ?? false));

        // Warteschlange neu starten, damit der Dienst «worker» die neue Einstellung lädt
        Artisan::call('queue:restart');

        if ($data['mailer'] === 'log') {
            return back()->with('status', 'Gespeichert: nur Protokoll, es werden keine Mails versendet (Registrierung bleibt aus).');
        }
        try {
            Mail::to((string) $data['an'])->sendNow(new TestMail);
        } catch (Throwable $e) {
            Log::warning('Testmail nach Einstellung fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
            Audit::log($user, $user, 'system.testmail', ['mailer' => 'administration'], 'failed');

            throw ValidationException::withMessages(['an' => 'Gespeichert, aber die Testmail ging nicht durch ('.class_basename($e).'). Einstellung prüfen; Details im Server-Log.']);
        }
        MailSettings::markVerified();
        Audit::log($user, $user, 'system.testmail', ['mailer' => 'administration']);

        return back()->with('status', 'Gespeichert und Testmail versendet – der Versand ist eingerichtet.');
    }

    /** Einstellung aus der Administration entfernen (#219). */
    public function removeMail(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        MailSettings::remove($user);
        Artisan::call('queue:restart');

        return back()->with('status', 'Einstellung entfernt – es gelten wieder die Vorgaben des Servers.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['an' => ['required', 'email', 'max:190']]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            Mail::to((string) $data['an'])->send(new TestMail);
        } catch (Throwable $e) {
            // sicheres Fehlerverhalten: keine Zugangsdaten oder Stacktraces anzeigen
            Log::warning('Testmail fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
            Audit::log($user, $user, 'system.testmail', ['mailer' => config('mail.default')], 'failed');

            throw ValidationException::withMessages(['an' => 'Versand fehlgeschlagen ('.class_basename($e).'). Details im Server-Log.']);
        }
        Audit::log($user, $user, 'system.testmail', ['mailer' => config('mail.default')]);

        return back()->with('status', config('queue.default') === 'sync'
            ? 'Testmail versendet (bzw. ins Log geschrieben, falls MAIL_MAILER=log).'
            : 'Testmail in die Warteschlange gestellt.');
    }
}
