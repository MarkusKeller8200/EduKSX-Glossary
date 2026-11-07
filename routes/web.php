<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExcelController;
use App\Http\Controllers\Admin\TermController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Öffentlich: nur lesen (PUB-01)
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('versionen', [PageController::class, 'versions'])->name('versions');
Route::get('begriffe/{slug}', [PageController::class, 'term'])->where('slug', '[a-z0-9-]+')->name('term');

// Interaktive Doku der öffentlichen API (OpenAPI 3.1, #26)
Route::view('api/docs', 'api-docs')->name('api.docs');

// Anmeldung lokaler Konten (DEC-014); keine Registrierung
Route::middleware('guest')->group(function () {
    Route::get('anmelden', [LoginController::class, 'create'])->name('login');
    Route::post('anmelden', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});
Route::post('abmelden', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Administration: alles, was Daten ändert, nur angemeldet und mit Recht
Route::middleware(['auth', 'can:administer'])->prefix('admin')->group(function () {
    Route::get('/', DashboardController::class)->name('admin.dashboard');
    Route::get('begriffe', [TermController::class, 'index'])->name('admin.terms');
    Route::post('begriffe/veroeffentlichen', [TermController::class, 'publish'])->name('admin.terms.publish');

    // Excel (#13, #15): Export, Vorlage, Import mit Vorschau
    Route::get('export', [ExcelController::class, 'export'])->name('admin.export');
    Route::get('import', [ExcelController::class, 'create'])->name('admin.import');
    Route::post('import', [ExcelController::class, 'preview'])->middleware('throttle:20,1')->name('admin.import.preview');
    Route::post('import/{token}', [ExcelController::class, 'apply'])->whereUuid('token')->name('admin.import.apply');
});
