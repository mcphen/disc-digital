<?php

use App\Http\Controllers\Administration\CourrierImportController;
use App\Http\Controllers\Administration\DiscSettingsController;
use App\Http\Controllers\Administration\UserController;
use App\Http\Controllers\BulkCourrierAssignmentController;
use App\Http\Controllers\CourrierAttachmentController;
use App\Http\Controllers\CourrierController;
use App\Http\Controllers\CourrierNotificationController;
use App\Http\Controllers\CourrierWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuarterlyDashboardController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('aide', function () {
    return Inertia::render('Help');
})->name('help');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('courriers/bulk-assignment', [BulkCourrierAssignmentController::class, 'index'])->name('courriers.bulk-assignment.index');
    Route::put('courriers/bulk-assignment', [BulkCourrierAssignmentController::class, 'store'])->name('courriers.bulk-assignment.store');
    Route::post('courriers/bulk-assignment/undo', [BulkCourrierAssignmentController::class, 'undo'])->name('courriers.bulk-assignment.undo');
    Route::resource('courriers', CourrierController::class)->only(['index', 'create', 'store', 'show']);
    Route::put('courriers/{courrier}/assignment', [CourrierWorkflowController::class, 'assign'])->name('courriers.assign');
    Route::put('courriers/{courrier}/submission', [CourrierWorkflowController::class, 'submit'])->name('courriers.submit');
    Route::put('courriers/{courrier}/review', [CourrierWorkflowController::class, 'review'])->name('courriers.review');
    Route::put('courriers/{courrier}/signature', [CourrierWorkflowController::class, 'sign'])->name('courriers.sign');
    Route::post('courriers/{courrier}/attachments', [CourrierAttachmentController::class, 'store'])->name('courriers.attachments.store');
    Route::get('attachments/{attachment}/download', [CourrierAttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [CourrierAttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('notifications', [CourrierNotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications/read-all', [CourrierNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::put('notifications/{notification}/read', [CourrierNotificationController::class, 'read'])->name('notifications.read');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/performance', [ReportController::class, 'performance'])->name('reports.performance');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('pilotage', [QuarterlyDashboardController::class, 'index'])->name('pilotage.index');
    Route::get('pilotage/divisions/{division}', [QuarterlyDashboardController::class, 'division'])->name('pilotage.division');
    Route::get('pilotage/agents/{agent}', [QuarterlyDashboardController::class, 'agent'])->name('pilotage.agent');

    Route::prefix('administration')->name('administration.')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::get('settings', [DiscSettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/alerts', [DiscSettingsController::class, 'updateThresholds'])->name('settings.alerts');
        Route::post('settings/divisions', [DiscSettingsController::class, 'storeDivision'])->name('settings.divisions.store');
        Route::put('settings/divisions/{division}', [DiscSettingsController::class, 'updateDivision'])->name('settings.divisions.update');
        Route::post('settings/types', [DiscSettingsController::class, 'storeType'])->name('settings.types.store');
        Route::put('settings/types/{courrierType}', [DiscSettingsController::class, 'updateType'])->name('settings.types.update');
        Route::get('imports', [CourrierImportController::class, 'index'])->name('imports.index');
        Route::post('imports/preview', [CourrierImportController::class, 'preview'])->name('imports.preview');
        Route::post('imports', [CourrierImportController::class, 'store'])->name('imports.store');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
