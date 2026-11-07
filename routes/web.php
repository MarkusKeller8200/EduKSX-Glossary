<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Öffentlich: nur lesen (PUB-01)
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('versionen', [PageController::class, 'versions'])->name('versions');

// Anmeldung lokaler Konten (DEC-014); keine Registrierung
Route::middleware('guest')->group(function () {
    Route::get('anmelden', [LoginController::class, 'create'])->name('login');
    Route::post('anmelden', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});
Route::post('abmelden', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Administration: alles, was Daten ändert, nur angemeldet und mit Recht
Route::middleware(['auth', 'can:administer'])->prefix('admin')->group(function () {
    Route::get('/', DashboardController::class)->name('admin.dashboard');
});
