<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\MeetingOutcomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/clients', [DashboardController::class, 'clients'])->name('clients.index');
    Route::get('/clients/{client}', [DashboardController::class, 'showClient'])->name('clients.show');
    Route::post('/clients', [DashboardController::class, 'storeClient'])->name('clients.store');
    Route::post('/appointments', [DashboardController::class, 'storeAppointment'])->name('appointments.store');
    Route::patch('/appointments/{appointment}', [DashboardController::class, 'updateAppointment'])->name('appointments.update');
    Route::post('/payments', [DashboardController::class, 'storePayment'])->name('payments.store');
    Route::post('/expenses', [DashboardController::class, 'storeExpense'])->name('expenses.store');
    Route::post('/clients/{client}/convert', [DashboardController::class, 'convert'])->name('clients.convert');

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

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});
