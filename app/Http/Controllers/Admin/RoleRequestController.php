<?php

namespace App\Http\Controllers\Admin;

use App\Access\AccessDenied;
use App\Access\RoleRequests;
use App\Http\Controllers\Controller;
use App\Models\OrganizationMember;
use App\Models\RoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Bewerbungen als Moderator/in (#460): alle Anfragen; entscheiden nur Konten mit «manage-users». */
class RoleRequestController extends Controller
{
    public function index(): Response
    {
        $orgs = OrganizationMember::query()->with('organization')->whereHas('organization')
            ->whereIn('user_id', RoleRequest::query()->select('user_id'))->get()
            ->mapWithKeys(fn (OrganizationMember $m): array => [$m->user_id => $m->organization->name]);
        $rows = RoleRequest::query()->with(['user', 'decidedBy'])
            ->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('created_at')->get()
            ->map(fn (RoleRequest $r): array => [
                'id' => $r->id,
                'konto' => ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email],
                'organisation' => $orgs[$r->user_id] ?? null,
                'begruendung' => $r->reason,
                'status' => $r->status,
                'am' => $r->created_at?->toDateString(),
                'entschieden_am' => $r->decided_at?->toDateString(),
                'entschieden_von' => $r->decidedBy?->name,
                'hinweis' => $r->decision_note,
                'voraussetzungen' => app(RoleRequests::class)->missing($r->user),
            ])->all();

        return Inertia::render('admin/role-requests', ['anfragen' => $rows]);
    }

    public function accept(Request $request, RoleRequest $roleRequest): RedirectResponse
    {
        return $this->decide($request, $roleRequest, true);
    }

    public function reject(Request $request, RoleRequest $roleRequest): RedirectResponse
    {
        return $this->decide($request, $roleRequest, false);
    }

    private function decide(Request $request, RoleRequest $roleRequest, bool $accept): RedirectResponse
    {
        $data = $request->validate(['hinweis' => ['nullable', 'string', 'max:1000']]);
        try {
            app(RoleRequests::class)->decide($this->currentUser($request), $roleRequest, $accept, $data['hinweis'] ?? null);
        } catch (AccessDenied $e) {
            throw ValidationException::withMessages(['hinweis' => $e->getMessage()]);
        }

        return back()->with('status', $accept ? 'Bewerbung angenommen – die Rolle Moderator ist vergeben.' : 'Bewerbung abgelehnt.');
    }
}
