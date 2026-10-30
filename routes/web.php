<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminTicketReportController;
use App\Http\Controllers\AdminSurveyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VerifierManagementController;
use App\Http\Controllers\WaTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'public.form'));
Route::get('/permohonan', [PublicTicketController::class, 'create'])->name('public.form');
Route::post('/permohonan', [PublicTicketController::class, 'store'])->middleware('throttle:5,10')->name('public.submit');
Route::post('/permohonan/cek-nik', [PublicTicketController::class, 'lookupNik'])->middleware('throttle:tte-nik-lookup')->name('public.lookup-nik');
Route::get('/permohonan/berhasil', [PublicTicketController::class, 'success'])->name('public.success');
// Signed URL 30 hari. Survei publik hanya melalui tautan dari notifikasi selesai.
Route::get('/survei/terima-kasih', [PublicSurveyController::class, 'thanks'])->name('public.survey.thanks');
Route::get('/survei/{ticket}', [PublicSurveyController::class, 'show'])->middleware(['signed', 'throttle:20,1'])->name('public.survey.show');
Route::post('/survei/{ticket}', [PublicSurveyController::class, 'store'])->middleware(['signed', 'throttle:5,10'])->name('public.survey.store');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.submit');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::patch('/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:10,1')->name('profile.update');
    Route::post('/profile/password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1')->name('profile.password');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/signature', [TicketController::class, 'signature'])->name('tickets.signature');
    Route::post('/tickets/{ticket}/claim', [TicketController::class, 'claim'])->name('tickets.claim');
    Route::post('/tickets/{ticket}/release', [TicketController::class, 'release'])->name('tickets.release');
    Route::post('/tickets/{ticket}/verify-whatsapp', [TicketController::class, 'verifyWhatsApp'])->name('tickets.verify-whatsapp');
    Route::post('/tickets/{ticket}/whatsapp-layanan', [TicketController::class, 'openServiceWhatsApp'])->name('tickets.whatsapp.service');
    Route::post('/tickets/{ticket}/whatsapp-layanan-confirm', [TicketController::class, 'confirmServiceWhatsApp'])->name('tickets.whatsapp.service.confirm');
    Route::post('/tickets/{ticket}/note', [TicketController::class, 'note'])->name('tickets.note');
    Route::post('/tickets/{ticket}/complete', [TicketController::class, 'complete'])->name('tickets.complete');
    Route::post('/tickets/{ticket}/whatsapp', [TicketController::class, 'openWhatsApp'])->name('tickets.whatsapp');
    Route::post('/tickets/{ticket}/whatsapp-confirm', [TicketController::class, 'confirmWhatsApp'])->name('tickets.whatsapp.confirm');
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/survei', [AdminSurveyController::class, 'index'])->name('admin.surveys.index');
    Route::get('/admin/laporan-tiket', [AdminTicketReportController::class, 'index'])->name('admin.reports.tickets.index');
    Route::get('/admin/laporan-tiket/csv', [AdminTicketReportController::class, 'csv'])->middleware('throttle:10,1')->name('admin.reports.tickets.csv');
    Route::get('/admin/wa-templates', [WaTemplateController::class, 'index'])->name('admin.templates.index');
    Route::put('/admin/wa-templates/{template}', [WaTemplateController::class, 'update'])->name('admin.templates.update');
    Route::get('/admin/verifiers', [VerifierManagementController::class, 'index'])->name('admin.verifiers.index');
    Route::post('/admin/verifiers', [VerifierManagementController::class, 'store'])->middleware('throttle:10,1')->name('admin.verifiers.store');
    Route::put('/admin/verifiers/{verifier}', [VerifierManagementController::class, 'update'])->middleware('throttle:20,1')->name('admin.verifiers.update');
    Route::delete('/admin/verifiers/{verifier}', [VerifierManagementController::class, 'destroy'])->middleware('throttle:10,1')->name('admin.verifiers.destroy');
    Route::post('/admin/verifiers/{user}/password-reset', [AdminController::class, 'sendResetLink'])->middleware('throttle:10,1')->name('admin.password-reset');
});
