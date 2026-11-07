<?php

namespace App\Http\Controllers\Admin;

use App\Access\AccessDenied;
use App\Access\Permission;
use App\Access\RoleManager;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Rollen und ihre Rechte (#38, docs/KONZEPT-BENUTZER.md 3): Matrix mit Schutzklassen, eigene Rollen anlegen. */
class RoleController extends Controller
{
    public function __construct(private readonly RoleManager $roles) {}

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $counts = DB::table('user_roles')->selectRaw('role_id, count(*) as n')->groupBy('role_id')->pluck('n', 'role_id');
        $own = $actor->roleKeys();

        $roles = Role::query()->orderBy('sort_order')->get()->map(fn (Role $r): array => [
            'id' => $r->id,
            'key' => $r->key,
            'name' => $r->name,
            'beschreibung' => $r->description,
            'system' => $r->key === Role::DEVELOPER,
            'eigene' => in_array($r->key, $own, true),
            'rechte' => $r->permissionKeys(),
            'konten' => (int) ($counts[$r->id] ?? 0),
        ])->all();

        $permissions = [];
        foreach (Permission::ALL as $key => $info) {
            $permissions[] = [
                'key' => $key,
                'label' => $info['label'],
                'klasse' => $info['class'],
                'eigenes' => $actor->hasPermission($key),
            ];
        }

        return Inertia::render('admin/roles', [
            'rollen' => $roles,
            'rechte' => $permissions,
            'developer' => $actor->isDeveloper(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'beschreibung' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            $this->roles->create($this->actor($request), (string) $data['name'], $data['beschreibung'] ?? null);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['name' => $e->getMessage()]);
        }

        return back()->with('status', 'Rolle angelegt – jetzt Rechte setzen.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate(['rechte' => ['array'], 'rechte.*' => ['string']]);
        try {
            $this->roles->setPermissions($this->actor($request), $role, array_values(array_map('strval', (array) ($data['rechte'] ?? []))));
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['rechte' => $e->getMessage()]);
        }

        return back()->with('status', "Rechte der Rolle «{$role->name}» gespeichert.");
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
