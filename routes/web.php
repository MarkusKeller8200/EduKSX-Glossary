<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExcelController;
use App\Http\Controllers\Admin\ListController;
use App\Http\Controllers\Admin\TermController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\PageController;
use App\Http\Middleware\RequireTwoFactor;
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
    Route::get('anmelden/code', [TwoFactorController::class, 'challenge'])->name('login.two-factor');
    Route::post('anmelden/code', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('login.two-factor.verify');
});
Route::post('abmelden', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Administration: alles, was Daten ändert, nur angemeldet und mit Recht
Route::middleware(['auth', 'can:administer', RequireTwoFactor::class])->prefix('admin')->group(function () {
    Route::get('/', DashboardController::class)->name('admin.dashboard');

    // Zweiter Faktor (#11): Pflicht, ohne ihn führt jede Seite hierher
    Route::get('zwei-faktor', [TwoFactorController::class, 'setup'])->name('admin.two-factor');
    Route::post('zwei-faktor', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('admin.two-factor.confirm');
    Route::post('zwei-faktor/codes', [TwoFactorController::class, 'recoveryCodes'])->name('admin.two-factor.codes');
    // Redaktion (#14)
    Route::get('begriffe', [TermController::class, 'index'])->name('admin.terms');
    Route::post('begriffe/veroeffentlichen', [TermController::class, 'publish'])->name('admin.terms.publish');
    Route::get('begriffe/neu', [TermController::class, 'create'])->name('admin.terms.create');
    Route::post('begriffe', [TermController::class, 'store'])->name('admin.terms.store');
    Route::get('begriffe/{term}', [TermController::class, 'show'])->whereUuid('term')->name('admin.terms.show');
    Route::get('begriffe/{term}/bearbeiten', [TermController::class, 'edit'])->whereUuid('term')->name('admin.terms.edit');
    Route::put('begriffe/{term}', [TermController::class, 'update'])->whereUuid('term')->name('admin.terms.update');
    Route::post('begriffe/{term}/veroeffentlichen', [TermController::class, 'publishOne'])->whereUuid('term')->name('admin.terms.publish-one');
    Route::post('begriffe/{term}/zurueckziehen', [TermController::class, 'unpublish'])->whereUuid('term')->name('admin.terms.unpublish');

    // Listen: Kategorien und Herkunft
    Route::get('listen', [ListController::class, 'index'])->name('admin.lists');
    Route::post('listen/{list}', [ListController::class, 'store'])->whereIn('list', ['kategorien', 'herkunft'])->name('admin.lists.store');
    Route::put('listen/{list}/{id}', [ListController::class, 'update'])->whereIn('list', ['kategorien', 'herkunft'])->whereUuid('id')->name('admin.lists.update');
    Route::delete('listen/{list}/{id}', [ListController::class, 'destroy'])->whereIn('list', ['kategorien', 'herkunft'])->whereUuid('id')->name('admin.lists.destroy');

    // Excel (#13, #15): Export, Vorlage, Import mit Vorschau
    Route::get('export', [ExcelController::class, 'export'])->name('admin.export');
    Route::get('import', [ExcelController::class, 'create'])->name('admin.import');
    Route::post('import', [ExcelController::class, 'preview'])->middleware('throttle:20,1')->name('admin.import.preview');
    Route::post('import/{token}', [ExcelController::class, 'apply'])->whereUuid('token')->name('admin.import.apply');
});
