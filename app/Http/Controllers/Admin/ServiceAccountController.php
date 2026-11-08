<?php

namespace App\Http\Controllers\Admin;

use App\Access\AccessDenied;
use App\Access\Service\ServiceAccountManager;
use App\Http\Controllers\Controller;
use App\Models\ServiceAccount;
use App\Models\ServiceAccountAudit;
use App\Models\ServiceAccountToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Servicekonten und Token (#47, Recht «service-tokens»): eigene Konten, mit «manage-users» alle. */
class ServiceAccountController extends Controller
{
    private const ACTIONS = [
        'service.created' => 'Servicekonto angelegt',
        'service.enabled' => 'Servicekonto entsperrt',
        'service.disabled' => 'Servicekonto gesperrt',
        'token.created' => 'Token erstellt',
        'token.revoked' => 'Token widerrufen',
        'token.used' => 'Zugriff mit Token',
    ];

    public function __construct(private readonly ServiceAccountManager $manager) {}

    public function index(Request $request): Response
    {
        $accounts = $this->manager->visibleFor($this->currentUser($request))->with(['responsible', 'organization'])->withCount([
            'tokens as aktive_token' => fn ($q) => $q->whereNull('revoked_at')->where('expires_at', '>', now()),
        ])->orderBy('name')->get()->map(fn (ServiceAccount $a): array => [
            'id' => $a->id,
            'name' => $a->name,
            'zweck' => $a->purpose,
            'verantwortlich' => self::responsible($a),
            'status' => $a->status,
            'scopes' => $a->scopes,
            'aktive_token' => (int) $a->getAttribute('aktive_token'),
        ])->all();

        return Inertia::render('admin/service-accounts', ['konten' => $accounts, 'scopes' => ServiceAccountManager::SCOPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'zweck' => ['required', 'string', 'max:255'],
            'scopes' => ['array'],
            'scopes.*' => ['string'],
        ]);
        try {
            $account = $this->manager->create($this->currentUser($request), (string) $data['name'], (string) $data['zweck'],
                array_values(array_map('strval', (array) ($data['scopes'] ?? []))));
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['scopes' => $e->getMessage()]);
        }

        return redirect("/admin/servicekonten/{$account->id}")->with('status', 'Servicekonto angelegt – jetzt ein Token erstellen.');
    }

    public function show(Request $request, ServiceAccount $account): Response
    {
        $actor = $this->currentUser($request);
        abort_unless($this->manager->canManage($actor, $account), 403);

        $names = User::query()->whereIn('id', ServiceAccountAudit::query()->where('service_account_id', $account->id)->whereNotNull('actor_id')->select('actor_id'))->pluck('name', 'id');

        return Inertia::render('admin/service-account-show', [
            'konto' => [
                'id' => $account->id,
                'name' => $account->name,
                'zweck' => $account->purpose,
                'verantwortlich' => self::responsible($account),
                'status' => $account->status,
                'scopes' => $account->scopes,
                'angelegt' => $account->created_at?->toDateString(),
            ],
            'token' => $account->tokens()->latest('created_at')->get()->map(fn (ServiceAccountToken $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'praefix' => $t->prefix,
                'bis' => $t->expires_at->toDateString(),
                'zuletzt' => $t->last_used_at?->toIso8601String(),
                'widerrufen' => $t->revoked_at?->toDateString(),
                'gueltig' => $t->isUsable(),
            ])->all(),
            // Klartext nur direkt nach dem Erstellen (Flash, einmalig)
            'neuesToken' => $request->session()->get('neues_token'),
            'protokoll' => ServiceAccountAudit::query()->where('service_account_id', $account->id)->latest('id')->limit(50)->get()
                ->map(fn (ServiceAccountAudit $e): array => [
                    // Namen einmal laden statt je Eintrag (#137)
                    'am' => $e->created_at->toIso8601String(),
                    'aktion' => self::ACTIONS[$e->action] ?? $e->action,
                    'von' => $e->actor_id !== null ? (string) ($names[$e->actor_id] ?? '(gelöscht)') : $e->actor_label,
                    'ergebnis' => $e->result,
                    'details' => $e->details,
                ])->all(),
            'scopeNamen' => ServiceAccountManager::SCOPES,
            'maxTage' => ServiceAccountManager::MAX_DAYS,
        ]);
    }

    public function createToken(Request $request, ServiceAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'tage' => ['required', 'integer', 'min:1', 'max:'.ServiceAccountManager::MAX_DAYS],
        ]);
        try {
            ['plain' => $plain] = $this->manager->createToken($this->currentUser($request), $account, (string) $data['name'], (int) $data['tage']);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return back()->with('neues_token', $plain)->with('status', 'Token erstellt – jetzt kopieren, es wird nur einmal angezeigt.');
    }

    public function revokeToken(Request $request, ServiceAccountToken $token): RedirectResponse
    {
        try {
            $this->manager->revokeToken($this->currentUser($request), $token);
        } catch (AccessDenied) {
            abort(403);
        }

        return back()->with('status', 'Token widerrufen.');
    }

    public function status(Request $request, ServiceAccount $account): RedirectResponse
    {
        $active = $request->boolean('aktiv');
        try {
            $this->manager->setStatus($this->currentUser($request), $account, $active);
        } catch (AccessDenied) {
            abort(403);
        }

        return back()->with('status', $active ? 'Servicekonto entsperrt.' : 'Servicekonto gesperrt – Token gelten nicht mehr.');
    }

    /** Verantwortliche Person, bei Servicekonten einer Organisation mit deren Namen (#148). */
    private static function responsible(ServiceAccount $account): ?string
    {
        $organization = $account->organization?->name;
        if ($organization === null) {
            return $account->responsible?->name;
        }

        return trim(($account->responsible->name ?? '').' (Organisation «'.$organization.'»)');
    }
}
