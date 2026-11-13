<?php

namespace App\Http\Controllers\Admin;

use App\Access\AccessDenied;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Source;
use App\Models\User;
use App\Organizations\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Papierkorb (#286): vorläufig gelöschte Hauptobjekte ansehen und wiederherstellen – nur Developer (Super-Admin). */
class TrashController extends Controller
{
    /** URL-Teil → [Model, Bezeichnung] */
    private const KINDS = [
        'kategorien' => [Category::class, 'Kategorie'],
        'herkunft' => [Source::class, 'Herkunft'],
        'organisationen' => [Organization::class, 'Organisation'],
    ];

    public function index(): Response
    {
        $rows = [];
        foreach (self::KINDS as $kind => [$model, $label]) {
            foreach ($model::onlyTrashed()->orderByDesc('deleted_at')->get() as $item) {
                $by = $item->deleted_by_id !== null ? User::query()->find($item->deleted_by_id) : null;
                $rows[] = [
                    'art' => $kind,
                    'art_label' => $label,
                    'id' => (string) $item->getKey(),
                    'name' => (string) $item->getAttribute('name'),
                    'geloescht_am' => $item->deleted_at?->toDateString(),
                    'von' => $by?->name,
                    'endgueltig_am' => $item->deleted_at?->addDays($model::trashDays())->toDateString(),
                ];
            }
        }

        return Inertia::render('admin/trash', ['eintraege' => $rows, 'tage' => Category::trashDays()]);
    }

    public function restore(Request $request, string $kind, string $id): RedirectResponse
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        [$model, $label] = self::KINDS[$kind];
        /** @var Category|Source|Organization $item */
        $item = $model::onlyTrashed()->findOrFail($id);
        if ($item instanceof Organization) {
            try {
                app(OrganizationService::class)->restore($this->currentUser($request), $item);
            } catch (AccessDenied $e) {
                throw ValidationException::withMessages(['papierkorb' => $e->getMessage()]);
            }
        } else {
            $this->restoreList($item);
        }

        return back()->with('status', "{$label} «{$item->name}» wiederhergestellt.");
    }

    private function restoreList(Category|Source $item): void
    {
        $item->restoreFromTrash();
    }
}
