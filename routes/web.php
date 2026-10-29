<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WaTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.submit');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::post('/profile/password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1')->name('profile.password');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/claim', [TicketController::class, 'claim'])->name('tickets.claim');
    Route::post('/tickets/{ticket}/release', [TicketController::class, 'release'])->name('tickets.release');
    Route::post('/tickets/{ticket}/note', [TicketController::class, 'note'])->name('tickets.note');
    Route::post('/tickets/{ticket}/complete', [TicketController::class, 'complete'])->name('tickets.complete');
    Route::post('/tickets/{ticket}/whatsapp-followup', [TicketController::class, 'openFollowUpWhatsApp'])->name('tickets.whatsapp.followup');
    Route::post('/tickets/{ticket}/whatsapp-followup-confirm', [TicketController::class, 'confirmFollowUpWhatsApp'])->name('tickets.whatsapp.followup.confirm');
    Route::post('/tickets/{ticket}/whatsapp', [TicketController::class, 'openWhatsApp'])->name('tickets.whatsapp');
    Route::post('/tickets/{ticket}/whatsapp-confirm', [TicketController::class, 'confirmWhatsApp'])->name('tickets.whatsapp.confirm');
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/wa-templates', [WaTemplateController::class, 'index'])->name('admin.templates.index');
    Route::put('/admin/wa-templates/{template}', [WaTemplateController::class, 'update'])->name('admin.templates.update');
    Route::post('/admin/verifiers/{user}/password-reset', [AdminController::class, 'sendResetLink'])->middleware('throttle:10,1')->name('admin.password-reset');
});
