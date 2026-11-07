<?php

use App\Http\Controllers\Api\GlossaryApiController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

// Öffentliche Lese-API, versioniert (docs/KONZEPT-API.md, openapi/glossary-v1.yaml). Nur veröffentlichte Daten.
// Schreibende Endpunkte (Vorschläge) folgen mit der Community-Funktion und nur mit Anmeldung.
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/', StatusController::class)->name('api.v1');
    Route::get('terms', [GlossaryApiController::class, 'terms'])->name('api.v1.terms');
    Route::get('terms/{idOrSlug}', [GlossaryApiController::class, 'term'])->where('idOrSlug', '[A-Za-z0-9-]+')->name('api.v1.term');
    Route::get('categories', [GlossaryApiController::class, 'categories'])->name('api.v1.categories');
    Route::get('sources', [GlossaryApiController::class, 'sources'])->name('api.v1.sources');
    Route::get('releases/latest', [GlossaryApiController::class, 'latestRelease'])->name('api.v1.releases.latest');
    Route::get('changes', [GlossaryApiController::class, 'changes'])->name('api.v1.changes');
    Route::get('exports', [GlossaryApiController::class, 'export'])->name('api.v1.exports');

    // Servicekonten (#47): Token prüfen; schreibende Endpunkte (Vorschläge, Scope «propose») folgen mit #113
    Route::get('service/whoami', [ServiceController::class, 'whoami'])->middleware('service.token')->name('api.v1.service.whoami');
});

// Maschinenlesbare Beschreibung (OpenAPI 3.1) als YAML und JSON
Route::get('openapi.yaml', [OpenApiController::class, 'yaml'])->name('api.openapi');
Route::get('openapi.json', [OpenApiController::class, 'json'])->name('api.openapi.json');
