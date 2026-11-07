<?php

namespace App\Http\Controllers\Admin;

use App\Access\Audit;
use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\User;
use App\Support\AppVersion;
use App\Support\MailSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        ]);
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
