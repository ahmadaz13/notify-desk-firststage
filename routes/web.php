<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CapitalExpenseController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConflictResolutionController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\MeetingOutcomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PublicClientController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Public Delegate Form (No Login Required)
Route::get('/p/{uuid}/client/create', [PublicClientController::class, 'create'])->name('public.client.create');
Route::post('/p/{uuid}/client', [PublicClientController::class, 'store'])->name('public.client.store');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Client Management
    Route::get('/clients', [DashboardController::class, 'clients'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::get('/clients/{client}', [DashboardController::class, 'showClient'])->name('clients.show');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::post('/clients', [DashboardController::class, 'storeClient'])->name('clients.store');
    Route::post('/clients/{client}/convert', [DashboardController::class, 'convert'])->name('clients.convert');

    // Appointments & Financial Transactions
    Route::post('/appointments', [DashboardController::class, 'storeAppointment'])->name('appointments.store');
    Route::patch('/appointments/{appointment}', [DashboardController::class, 'updateAppointment'])->name('appointments.update');
    Route::post('/payments', [DashboardController::class, 'storePayment'])->name('payments.store');
    Route::post('/expenses', [DashboardController::class, 'storeExpense'])->name('expenses.store');
    Route::post('/investments', [InvestmentController::class, 'store'])->name('investments.store');
    Route::post('/capital-expenses', [CapitalExpenseController::class, 'store'])->name('capital-expenses.store');

    // Partner Management (Admin Only)
    Route::resource('partners', PartnerController::class);

    // Conflict Resolution (Admin Only)
    Route::get('/conflicts', [ConflictResolutionController::class, 'index'])->name('conflicts.index');
    Route::get('/conflicts/{conflict}', [ConflictResolutionController::class, 'show'])->name('conflicts.show');
    Route::post('/conflicts/{conflict}/resolve', [ConflictResolutionController::class, 'resolve'])->name('conflicts.resolve');

    // Meeting Outcomes
    Route::get('/appointments/{appointment}/outcome', [MeetingOutcomeController::class, 'create'])->name('appointments.outcome.create');
    Route::post('/appointments/{appointment}/outcome', [MeetingOutcomeController::class, 'store'])->name('appointments.outcome.store');

    // Follow-ups & Offers
    Route::post('/clients/{client}/follow-ups', [FollowUpController::class, 'store'])->name('clients.follow-ups.store');
    Route::post('/clients/{client}/offers', [OfferController::class, 'store'])->name('clients.offers.store');

    // CSV Import
    Route::get('/clients-import', [CsvImportController::class, 'index'])->name('clients.import');
    Route::post('/clients-import/preview', [CsvImportController::class, 'preview'])->name('clients.import.preview');
    Route::post('/clients-import/confirm', [CsvImportController::class, 'confirm'])->name('clients.import.confirm');
    Route::get('/clients-import/template/{type}', [CsvImportController::class, 'downloadTemplate'])->name('clients.import.template');

    // Admin Settings & Control Center
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/update', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/export', [SettingsController::class, 'export'])->name('settings.export');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});
