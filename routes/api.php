<?php

use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

// Öffentliche Lese-API, versioniert (docs/KONZEPT-API.md). Schreibende Endpunkte nur mit Anmeldung.
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/', StatusController::class)->name('api.v1');
});

// Maschinenlesbare Beschreibung (OpenAPI 3.1)
Route::get('openapi.yaml', OpenApiController::class)->name('api.openapi');
