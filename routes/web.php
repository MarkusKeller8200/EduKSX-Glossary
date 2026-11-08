<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DuplicateController;
use App\Http\Controllers\Admin\ExcelController;
use App\Http\Controllers\Admin\LegacyValueController;
use App\Http\Controllers\Admin\ListController;
use App\Http\Controllers\Admin\ProposalController as AdminProposalController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServiceAccountController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SourceCheckController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TermController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationDisplayController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PersonalDisplayController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProposalController;
use App\Http\Middleware\RequireTwoFactor;
use Illuminate\Support\Facades\Route;

// Öffentlich: nur lesen (PUB-01)
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('versionen', [PageController::class, 'versions'])->name('versions');
Route::get('legals', [PageController::class, 'legals'])->name('legals');
Route::get('hilfe', [PageController::class, 'help'])->name('help');
// Einbindung für Entwickler (#155, DEC-026)
Route::get('entwickler', [PageController::class, 'developers'])->name('developers');
Route::get('entwickler/datenmodell.sql', [PageController::class, 'schemaSql'])->middleware('throttle:20,1')->name('developers.schema');
// Datenschutzerklärung (#42); Kenntnisnahme für angemeldete Konten
Route::get('datenschutz', [PrivacyController::class, 'show'])->name('privacy');
Route::middleware('auth')->group(function () {
    Route::get('datenschutz/kenntnisnahme', [PrivacyController::class, 'acknowledge'])->name('privacy.acknowledge');
    Route::post('datenschutz/kenntnisnahme', [PrivacyController::class, 'store'])->name('privacy.store');
});
// Permalink je Begriff (#154, DEC-026): «g» für Glossary
Route::get('g/{code}', [PageController::class, 'permalink'])->where('code', '[0-9A-Za-z]{6}')->name('permalink');
Route::get('begriffe/{slug}', [PageController::class, 'term'])->where('slug', '[a-z0-9-]+')->name('term');
// Vorschläge (#113, DEC-024): ab Benutzer (Recht «propose»), nur eigene Vorschläge sichtbar
Route::middleware(['auth', 'can:propose'])->prefix('vorschlaege')->group(function () {
    Route::get('/', [ProposalController::class, 'index'])->name('proposals');
    Route::get('neu', [ProposalController::class, 'create'])->name('proposals.create');
    Route::post('/', [ProposalController::class, 'store'])->middleware('throttle:10,1')->name('proposals.store');
    Route::get('{proposal}', [ProposalController::class, 'show'])->whereNumber('proposal')->name('proposals.show');
    Route::post('{proposal}/antwort', [ProposalController::class, 'answer'])->whereNumber('proposal')->middleware('throttle:20,1')->name('proposals.answer');
    Route::post('{proposal}/zurueckziehen', [ProposalController::class, 'withdraw'])->whereNumber('proposal')->name('proposals.withdraw');
});

// Export veröffentlichter Daten (#111): ab Benutzer, ausserhalb der Administration
Route::get('export', ExportController::class)->middleware(['auth', 'can:export', 'throttle:20,1'])->name('export');
Route::post('export', [ExportController::class, 'selection'])->middleware(['auth', 'can:export', 'throttle:30,1'])->name('export.selection');

// Meine Darstellung (DEC-020): persönliche Anpassung eines Begriffs, nur für das eigene Konto, ab Benutzer (#114)
Route::middleware(['auth', 'can:personal-display', 'throttle:30,1'])->group(function () {
    Route::put('begriffe/{slug}/meine-darstellung', [PersonalDisplayController::class, 'update'])->where('slug', '[a-z0-9-]+')->name('term.personal');
    Route::delete('begriffe/{slug}/meine-darstellung', [PersonalDisplayController::class, 'destroy'])->where('slug', '[a-z0-9-]+')->name('term.personal.destroy');
});

// Organisation (#148, DEC-025): für angemeldete Konten; Regeln im OrganizationService
Route::middleware(['auth', 'throttle:30,1'])->prefix('organisation')->group(function () {
    Route::get('/', [OrganizationController::class, 'show'])->withoutMiddleware('throttle:30,1')->name('organization');
    Route::post('/', [OrganizationController::class, 'store'])->name('organization.store');
    Route::put('/', [OrganizationController::class, 'update'])->name('organization.update');
    Route::post('loeschen', [OrganizationController::class, 'destroy'])->name('organization.destroy');
    Route::post('austreten', [OrganizationController::class, 'leave'])->name('organization.leave');
    Route::post('einladungen', [OrganizationController::class, 'invite'])->middleware('throttle:10,1')->name('organization.invite');
    Route::delete('einladungen/{invitation}', [OrganizationController::class, 'revokeInvitation'])->whereNumber('invitation')->name('organization.invitation.destroy');
    Route::post('einladungen/{invitation}/annehmen', [OrganizationController::class, 'accept'])->whereNumber('invitation')->name('organization.invitation.accept');
    Route::post('einladungen/{invitation}/ablehnen', [OrganizationController::class, 'decline'])->whereNumber('invitation')->name('organization.invitation.decline');
    Route::post('mitglieder/{member}/sperren', [OrganizationController::class, 'block'])->whereNumber('member')->name('organization.member.block');
    Route::post('mitglieder/{member}/entsperren', [OrganizationController::class, 'unblock'])->whereNumber('member')->name('organization.member.unblock');
    Route::put('mitglieder/{member}/rolle', [OrganizationController::class, 'role'])->whereNumber('member')->name('organization.member.role');
    Route::delete('mitglieder/{member}', [OrganizationController::class, 'remove'])->whereNumber('member')->name('organization.member.destroy');
    Route::post('vorschlaege/{proposal}/bestaetigen', [OrganizationController::class, 'confirmProposal'])->whereNumber('proposal')->name('organization.proposal.confirm');
    Route::post('darstellungen/{override}/freigeben', [OrganizationController::class, 'approvePersonal'])->whereUuid('override')->name('organization.personal.approve');
    Route::post('darstellungen/{override}/ablehnen', [OrganizationController::class, 'rejectPersonal'])->whereUuid('override')->name('organization.personal.reject');
    Route::post('vorschlaege/{proposal}/ablehnen', [OrganizationController::class, 'rejectProposal'])->whereNumber('proposal')->name('organization.proposal.reject');
});
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::put('begriffe/{slug}/organisation-darstellung', [OrganizationDisplayController::class, 'update'])->where('slug', '[a-z0-9-]+')->name('term.organization');
    Route::post('begriffe/{slug}/organisation-darstellung/aus-meiner', [OrganizationDisplayController::class, 'fromPersonal'])->where('slug', '[a-z0-9-]+')->name('term.organization.from-personal');
    Route::delete('begriffe/{slug}/organisation-darstellung', [OrganizationDisplayController::class, 'destroy'])->where('slug', '[a-z0-9-]+')->name('term.organization.destroy');
});

// Interaktive Doku der öffentlichen API (OpenAPI 3.1, #26)
Route::view('api/docs', 'api-docs')->name('api.docs');
// Swagger-Seite mit «Try it out» (#157)
Route::view('api/swagger', 'api-swagger')->name('api.swagger');

// Anmeldung lokaler Konten (DEC-014); keine Registrierung
Route::middleware('guest')->group(function () {
    Route::get('anmelden', [LoginController::class, 'create'])->name('login');
    Route::post('anmelden', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('anmelden/code', [TwoFactorController::class, 'challenge'])->name('login.two-factor');
    Route::post('anmelden/code', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('login.two-factor.verify');

    // Selbstregistrierung mit Double-Opt-in und «Passwort vergessen» (#41); nur mit eingerichtetem Mailversand
    Route::get('registrieren', [RegisterController::class, 'create'])->name('register');
    Route::post('registrieren', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('passwort-vergessen', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('passwort-vergessen', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('passwort-zuruecksetzen/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('passwort-zuruecksetzen', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});
Route::post('abmelden', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
// Anmeldung mit Microsoft (#45): auch für angemeldete Konten (Verknüpfen nach Passwortbestätigung)
Route::get('anmelden/microsoft', [SsoController::class, 'redirect'])->middleware('throttle:20,1')->name('sso.microsoft');
Route::get('anmelden/microsoft/rueckkehr', [SsoController::class, 'callback'])->middleware('throttle:20,1')->name('sso.microsoft.callback');

// Bestätigungslink der Registrierung (signiert, 60 Minuten; ohne Anmeldung)
Route::get('registrieren/bestaetigen/{user}/{hash}', [RegisterController::class, 'verify'])
    ->middleware(['signed', 'throttle:10,1'])->whereUuid('user')->name('register.verify');

// Mein Konto (#39): für alle angemeldeten Konten, auch Community
Route::middleware('auth')->prefix('konto')->group(function () {
    Route::get('/', [AccountController::class, 'show'])->name('account');
    Route::put('/', [AccountController::class, 'update'])->name('account.update');
    Route::post('news', [AccountController::class, 'news'])->middleware('throttle:10,1')->name('account.news');
    Route::put('passwort', [AccountController::class, 'password'])->middleware('throttle:10,1')->name('account.password');
    Route::post('loeschen', [AccountController::class, 'destroy'])->middleware('throttle:5,1')->name('account.destroy');
    Route::post('anmeldedienste/microsoft', [SsoController::class, 'startLink'])->middleware('throttle:5,1')->name('account.identities.microsoft');
    Route::delete('anmeldedienste/{identity}', [SsoController::class, 'unlink'])->whereNumber('identity')->middleware('throttle:5,1')->name('account.identities.destroy');

    // Zweiter Faktor (#11, #37): Pflicht für privilegierte Rechte, sonst freiwillig
    Route::get('zwei-faktor', [TwoFactorController::class, 'setup'])->name('account.two-factor');
    Route::post('zwei-faktor', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('account.two-factor.confirm');
    Route::post('zwei-faktor/codes', [TwoFactorController::class, 'recoveryCodes'])->name('account.two-factor.codes');
});

// Administration: nur angemeldet, mit einem Recht ausser «Vorschläge einreichen» und mit zweitem Faktor; jede
// Aktion zusätzlich mit ihrem Recht (#36)
Route::middleware(['auth', 'can:administer', RequireTwoFactor::class])->prefix('admin')->group(function () {
    Route::get('/', DashboardController::class)->name('admin.dashboard');

    // Redaktion (#14)
    Route::get('begriffe', [TermController::class, 'index'])->middleware('can:edit-terms')->name('admin.terms');
    Route::post('begriffe/veroeffentlichen', [TermController::class, 'publish'])->middleware('can:publish')->name('admin.terms.publish');
    Route::get('begriffe/neu', [TermController::class, 'create'])->middleware('can:edit-terms')->name('admin.terms.create');
    Route::post('begriffe', [TermController::class, 'store'])->middleware('can:edit-terms')->name('admin.terms.store');
    Route::get('begriffe/{term}', [TermController::class, 'show'])->whereUuid('term')->middleware('can:edit-terms')->name('admin.terms.show');
    Route::get('begriffe/{term}/bearbeiten', [TermController::class, 'edit'])->whereUuid('term')->middleware('can:edit-terms')->name('admin.terms.edit');
    Route::put('begriffe/{term}', [TermController::class, 'update'])->whereUuid('term')->middleware('can:edit-terms')->name('admin.terms.update');
    Route::patch('begriffe/{term}/feld', [TermController::class, 'updateField'])->whereUuid('term')->middleware('can:edit-terms')->name('admin.terms.field');
    Route::post('begriffe/{term}/veroeffentlichen', [TermController::class, 'publishOne'])->whereUuid('term')->middleware('can:publish')->name('admin.terms.publish-one');
    Route::post('begriffe/{term}/wiederherstellen', [TermController::class, 'restore'])->whereUuid('term')->middleware('can:edit-terms')->name('admin.terms.restore');
    Route::post('begriffe/{term}/zurueckziehen', [TermController::class, 'unpublish'])->whereUuid('term')->middleware('can:publish')->name('admin.terms.unpublish');

    // Doppelte (#16): anzeigen, zusammenführen oder als mehrdeutig belassen
    Route::middleware('can:edit-terms')->group(function () {
        Route::get('doppelte', [DuplicateController::class, 'index'])->name('admin.duplicates');
        Route::post('doppelte/belassen', [DuplicateController::class, 'dismiss'])->name('admin.duplicates.dismiss');
        Route::post('doppelte/zuruecksetzen', [DuplicateController::class, 'reset'])->name('admin.duplicates.reset');
        Route::get('doppelte/zusammenfuehren', [DuplicateController::class, 'create'])->name('admin.duplicates.merge');
        Route::post('doppelte/zusammenfuehren', [DuplicateController::class, 'store'])->name('admin.duplicates.merge.store');

        // Quellenprüfung (#69)
        Route::get('pruefung', [SourceCheckController::class, 'index'])->name('admin.source-check');
        Route::put('begriffe/{term}/herkunft', [SourceCheckController::class, 'update'])->whereUuid('term')->name('admin.terms.sources');

        // Altwerte aus dem Intranet-Format zuordnen (DEC-020)
        Route::get('altwerte', [LegacyValueController::class, 'index'])->name('admin.legacy');
        Route::post('altwerte', [LegacyValueController::class, 'resolve'])->name('admin.legacy.resolve');
        Route::get('altwerte/intern-genutzt-in.csv', [LegacyValueController::class, 'exportUsedIn'])->name('admin.legacy.used-in');
    });

    // Listen: Kategorien und Herkunft
    Route::middleware('can:lists')->group(function () {
        Route::get('listen', [ListController::class, 'index'])->name('admin.lists');
        Route::get('listen/{list}/export', [ListController::class, 'export'])->whereIn('list', ['kategorien', 'herkunft'])->name('admin.lists.export');
        Route::post('listen/{list}/import', [ListController::class, 'preview'])->whereIn('list', ['kategorien', 'herkunft'])->middleware('throttle:20,1')->name('admin.lists.import');
        Route::post('listen/{list}/import/{token}', [ListController::class, 'apply'])->whereIn('list', ['kategorien', 'herkunft'])->whereUuid('token')->name('admin.lists.import.apply');
        Route::post('listen/{list}', [ListController::class, 'store'])->whereIn('list', ['kategorien', 'herkunft'])->name('admin.lists.store');
        Route::put('listen/{list}/{id}', [ListController::class, 'update'])->whereIn('list', ['kategorien', 'herkunft'])->whereUuid('id')->name('admin.lists.update');
        Route::delete('listen/{list}/{id}', [ListController::class, 'destroy'])->whereIn('list', ['kategorien', 'herkunft'])->whereUuid('id')->name('admin.lists.destroy');
    });

    // Benutzer und Rollen (#38): nur mit «manage-users»; Schutzregeln im RoleManager/AccountManager
    Route::middleware('can:manage-users')->group(function () {
        Route::get('benutzer', [UserController::class, 'index'])->name('admin.users');
        Route::post('benutzer', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('benutzer/{user}', [UserController::class, 'show'])->whereUuid('user')->name('admin.users.show');
        Route::put('benutzer/{user}/rollen', [UserController::class, 'updateRoles'])->whereUuid('user')->name('admin.users.roles');
        Route::post('benutzer/{user}/sperren', [UserController::class, 'block'])->whereUuid('user')->name('admin.users.block');
        Route::post('benutzer/{user}/entsperren', [UserController::class, 'unblock'])->whereUuid('user')->name('admin.users.unblock');
        Route::post('benutzer/{user}/passwort-link', [UserController::class, 'sendPasswordReset'])->whereUuid('user')->middleware('throttle:10,1')->name('admin.users.password-link');
        Route::post('benutzer/{user}/zwei-faktor', [UserController::class, 'resetTwoFactor'])->whereUuid('user')->name('admin.users.reset-2fa');
        Route::get('rollen', [RoleController::class, 'index'])->name('admin.roles');
        Route::post('rollen', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::put('rollen/{role}', [RoleController::class, 'update'])->whereUuid('role')->name('admin.roles.update');
    });

    // Moderation der Vorschläge (#113)
    Route::middleware('can:moderate')->group(function () {
        Route::get('vorschlaege', [AdminProposalController::class, 'index'])->name('admin.proposals');
        Route::get('vorschlaege/{proposal}', [AdminProposalController::class, 'show'])->whereNumber('proposal')->name('admin.proposals.show');
        Route::post('vorschlaege/{proposal}/annehmen', [AdminProposalController::class, 'accept'])->whereNumber('proposal')->name('admin.proposals.accept');
        Route::post('vorschlaege/{proposal}/ablehnen', [AdminProposalController::class, 'reject'])->whereNumber('proposal')->name('admin.proposals.reject');
        Route::post('vorschlaege/{proposal}/rueckfrage', [AdminProposalController::class, 'ask'])->whereNumber('proposal')->name('admin.proposals.ask');
    });

    // Servicekonten mit Token (#47): ab Organisations-Admin
    Route::middleware('can:service-tokens')->group(function () {
        Route::get('servicekonten', [ServiceAccountController::class, 'index'])->name('admin.service');
        Route::post('servicekonten', [ServiceAccountController::class, 'store'])->name('admin.service.store');
        Route::get('servicekonten/{account}', [ServiceAccountController::class, 'show'])->whereUuid('account')->name('admin.service.show');
        Route::post('servicekonten/{account}/token', [ServiceAccountController::class, 'createToken'])->whereUuid('account')->middleware('throttle:10,1')->name('admin.service.token');
        Route::post('servicekonten/{account}/status', [ServiceAccountController::class, 'status'])->whereUuid('account')->name('admin.service.status');
        Route::delete('servicekonten/token/{token}', [ServiceAccountController::class, 'revokeToken'])->whereUuid('token')->name('admin.service.revoke');
    });

    // Allgemeine Einstellungen (#106): Recht «settings» (systemkritisch)
    Route::middleware('can:settings')->group(function () {
        Route::get('einstellungen', [SettingsController::class, 'index'])->name('admin.settings');
        Route::put('einstellungen', [SettingsController::class, 'update'])->name('admin.settings.update');
    });

    // System (#40): nur Developer (Recht «system»)
    Route::middleware('can:system')->group(function () {
        Route::get('system', [SystemController::class, 'index'])->name('admin.system');
        Route::post('system/testmail', [SystemController::class, 'testMail'])->middleware('throttle:5,1')->name('admin.system.testmail');
    });

    // Excel (#13, #15, #111): Export des Arbeitsstands (Redaktion) getrennt vom Import mit Vorschau
    Route::get('export', [ExcelController::class, 'export'])->middleware(['can:edit-terms', 'can:export'])->name('admin.export');
    Route::post('export', [ExcelController::class, 'exportSelection'])->middleware(['can:edit-terms', 'can:export', 'throttle:30,1'])->name('admin.export.selection');
    Route::middleware('can:import')->group(function () {
        Route::get('import', [ExcelController::class, 'create'])->name('admin.import');
        Route::post('import', [ExcelController::class, 'preview'])->middleware('throttle:20,1')->name('admin.import.preview');
        Route::post('import/{token}', [ExcelController::class, 'apply'])->whereUuid('token')->name('admin.import.apply');
    });
});
