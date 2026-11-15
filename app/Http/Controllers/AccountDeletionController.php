<?php

namespace App\Http\Controllers;

use App\Access\AccessDenied;
use App\Access\AccountManager;
use App\Mail\ConfirmAccountDeletion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Konto löschen ohne eigenes Passwort (#445, Security-Review SEC-01): Wer sich nur mit Microsoft anmeldet, bestätigt
 * das Löschen über einen Link an die eigene Adresse (signiert, 60 Minuten). Die Seite des Links verlangt nochmals
 * einen Klick (Mailprogramme rufen Links teils selbst auf). Eine übernommene Sitzung allein genügt so nicht mehr.
 */
class AccountDeletionController extends Controller
{
    public const MINUTES = 60;

    public static function sendLink(User $user): void
    {
        $url = URL::temporarySignedRoute('account.delete.confirm', now()->addMinutes(self::MINUTES), [
            'user' => $user->id,
            'hash' => self::fingerprint($user),
        ]);
        try {
            Mail::to($user->email)->send(new ConfirmAccountDeletion($url, self::MINUTES));
        } catch (Throwable $e) {
            Log::warning('Mail «Konto löschen» fehlgeschlagen', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request, User $user, string $hash): Response|RedirectResponse
    {
        if (! $this->valid($user, $hash)) {
            return redirect('/')->with('status', 'Der Link ist abgelaufen oder wurde schon verwendet.');
        }

        return Inertia::render('account/delete-confirm', ['aktion' => $request->fullUrl(), 'email' => $user->email]);
    }

    public function destroy(Request $request, User $user, string $hash, AccountManager $accounts): RedirectResponse
    {
        if (! $this->valid($user, $hash)) {
            return redirect('/')->with('status', 'Der Link ist abgelaufen oder wurde schon verwendet.');
        }
        try {
            $accounts->delete($user, $user);
        } catch (AccessDenied $e) {
            return redirect('/')->with('status', $e->getMessage());
        }
        if ($request->user()?->getAuthIdentifier() === $user->id) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/')->with('status', 'Konto gelöscht.');
    }

    private function valid(User $user, string $hash): bool
    {
        return $user->isActive() && ! $user->has_password && hash_equals(self::fingerprint($user), $hash);
    }

    private static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', 'konto-loeschen|'.$user->id.'|'.mb_strtolower($user->email), (string) config('app.key')), 0, 40);
    }
}
