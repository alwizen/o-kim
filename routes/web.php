<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\InternalRegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PendingInternalController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\FindingAttachmentController;
use App\Http\Controllers\FindingReportController;
use App\Http\Controllers\InspectionReportController;
use App\Http\Controllers\InspectionResultAttachmentController;
use App\Http\Controllers\KimDownloadController;
use App\Http\Controllers\KimDownloadImageController;
use App\Http\Controllers\KimValidationController;
use App\Http\Controllers\TransporterRegistrationController;
use App\Http\Controllers\VehicleDocumentDownloadController;
use App\Http\Controllers\VehicleDocumentPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing.index');
})->name('landing');

Route::get('/login', fn () => redirect()->route('login.form'))->name('login');

// OAuth Google — rate limit ketat (PRD 5.4)
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

// Registrasi & login email + password — tetap wajib approval admin
Route::middleware('guest')->group(function () {
    Route::get('/daftar', [RegisterController::class, 'show'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/daftar/internal', [InternalRegisterController::class, 'show'])->name('register.internal');
    Route::post('/daftar/internal', [InternalRegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/masuk', [LoginController::class, 'show'])->name('login.form');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.attempt');

    // Reset kata sandi — hanya untuk akun email + kata sandi (bukan akun Google).
    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-kata-sandi/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-kata-sandi', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Halaman tunggu akun internal yang belum disetujui
Route::get('/menunggu-verifikasi', [PendingInternalController::class, 'show'])
    ->middleware('auth')
    ->name('registration.pending.internal');

// Halaman tunggu transportir yang sudah mengirim profil perusahaan
Route::get('/menunggu-verifikasi-transportir', [TransporterRegistrationController::class, 'pending'])
    ->middleware('auth')
    ->name('registration.pending.transporter');

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/validasi-kim/{token}', [KimValidationController::class, 'show'])
        ->name('kim.validate');
});

Route::middleware(['auth', 'account.approved'])->group(function () {
    Route::get('/pendaftaran/profil', [TransporterRegistrationController::class, 'edit'])
        ->name('registration.profile.edit');
    Route::post('/pendaftaran/profil', [TransporterRegistrationController::class, 'update'])
        ->name('registration.profile.update');

    Route::get('/dokumen/{document}/unduh', VehicleDocumentDownloadController::class)
        ->name('documents.download');

    Route::get('/dokumen/{document}/pratinjau', VehicleDocumentPreviewController::class)
        ->name('documents.preview');

    Route::get('/tindak-lanjut/{attachment}/pratinjau', [FindingAttachmentController::class, 'preview'])
        ->name('finding-attachments.preview');

    Route::get('/tindak-lanjut/{attachment}/unduh', [FindingAttachmentController::class, 'download'])
        ->name('finding-attachments.download');

    Route::get('/bukti-pemeriksaan/{attachment}/pratinjau', [InspectionResultAttachmentController::class, 'preview'])
        ->name('inspection-attachments.preview');

    Route::get('/bukti-pemeriksaan/{attachment}/unduh', [InspectionResultAttachmentController::class, 'download'])
        ->name('inspection-attachments.download');

    Route::get('/kim/{card}/unduh', KimDownloadController::class)
        ->name('kim.download');

    Route::get('/kim/{card}/unduh-gambar', KimDownloadImageController::class)
        ->name('kim.download-image');

    // Laporan hasil pemeriksaan checklist (printout PDF)
    Route::get('/pengajuan/{application}/laporan-pemeriksaan', [InspectionReportController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('inspection-report.download');

    Route::get('/pengajuan/{application}/laporan-pemeriksaan/pratinjau', [InspectionReportController::class, 'preview'])
        ->middleware('throttle:30,1')
        ->name('inspection-report.preview');

    // Berita acara tindak lanjut temuan (printout PDF)
    Route::get('/pengajuan/{application}/berita-acara', [FindingReportController::class, 'download'])
        ->middleware('throttle:30,1')
        ->name('berita-acara.download');

    Route::get('/pengajuan/{application}/berita-acara/pratinjau', [FindingReportController::class, 'preview'])
        ->middleware('throttle:30,1')
        ->name('berita-acara.preview');
});
