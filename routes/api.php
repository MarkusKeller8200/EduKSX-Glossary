<?php

use App\Http\Controllers\Api\GlossaryApiController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\OrganizationApiController;
use App\Http\Controllers\Api\ProposalApiController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

// Öffentliche Lese-API, versioniert (docs/KONZEPT-API.md, openapi/glossary-v1.yaml). Nur veröffentlichte Daten.
// Schreibend nur Vorschläge mit Token eines Servicekontos (#47, #113).
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/', StatusController::class)->name('api.v1');
    Route::get('terms', [GlossaryApiController::class, 'terms'])->name('api.v1.terms');
    Route::get('terms/{idOrSlug}', [GlossaryApiController::class, 'term'])->where('idOrSlug', '[A-Za-z0-9-]+')->name('api.v1.term');
    Route::get('index', [GlossaryApiController::class, 'index'])->name('api.v1.index');
    Route::get('categories', [GlossaryApiController::class, 'categories'])->name('api.v1.categories');
    Route::get('sources', [GlossaryApiController::class, 'sources'])->name('api.v1.sources');
    Route::get('releases/latest', [GlossaryApiController::class, 'latestRelease'])->name('api.v1.releases.latest');
    Route::get('changes', [GlossaryApiController::class, 'changes'])->name('api.v1.changes');
    Route::get('exports', [GlossaryApiController::class, 'export'])->name('api.v1.exports');

    // Servicekonten (#47): Token prüfen; Vorschläge mit Scope «propose» (#113), landen in der Moderation
    Route::get('service/whoami', [ServiceController::class, 'whoami'])->middleware('service.token')->name('api.v1.service.whoami');
    Route::post('proposals', [ProposalApiController::class, 'store'])->middleware(['service.token:propose', 'throttle:30,1'])->name('api.v1.proposals.store');
    // Organisation (#148): Begriffe in deren Darstellung, Token mit Scope «read-org»
    Route::get('org/terms', [OrganizationApiController::class, 'terms'])->middleware('service.token:read-org')->name('api.v1.org.terms');
    Route::get('org/terms/{idOrSlug}', [OrganizationApiController::class, 'term'])->where('idOrSlug', '[A-Za-z0-9-]+')->middleware('service.token:read-org')->name('api.v1.org.term');
    Route::get('proposals/{id}', [ProposalApiController::class, 'show'])->whereNumber('id')->middleware('service.token:propose')->name('api.v1.proposals.show');
});

// Maschinenlesbare Beschreibung (OpenAPI 3.1) als YAML und JSON
Route::get('openapi.yaml', [OpenApiController::class, 'yaml'])->name('api.openapi');
Route::get('openapi.json', [OpenApiController::class, 'json'])->name('api.openapi.json');
// Postman-Sammlung zum Import (#158)
Route::get('postman.json', [OpenApiController::class, 'postman'])->name('api.postman');
