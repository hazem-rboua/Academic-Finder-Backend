<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Company\ProfileController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\AcademicFinderPdfController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

// Invitation routes (public)
Route::prefix('invitations')->group(function () {
    Route::get('/validate/{token}', [InvitationController::class, 'validate']);
    Route::post('/accept/{token}', [InvitationController::class, 'accept']);
});

// Trusted callers only (App\Http\Middleware\TrustedCaller): the Twindix API with its
// X-Service-Key, or a user logged in to this app.
Route::middleware('trusted')->group(function () {
    // Academic Finder PDF for the Twindix API (same report design as the Twindix API)
    Route::post('/generate-pdf', [PdfController::class, 'generate']);
    // A PDF made by generate-pdf; deleted once downloaded.
    Route::get('/reports/{file}', [PdfController::class, 'download'])->where('file', '[A-Za-z0-9_-]+\.pdf');

    // Exam results routes
    Route::prefix('exam-results')->group(function () {
        Route::post('/process', [ExamResultController::class, 'process']);
        Route::post('/process-direct', [ExamResultController::class, 'processDirect']);
        Route::get('/status/{jobId}', [ExamResultController::class, 'status']);
        // ADDITIVE 2026-06-16 — Academic Finder PDF generation
        Route::post('/generate', [AcademicFinderPdfController::class, 'generate']);
        Route::get('/report-status', [AcademicFinderPdfController::class, 'reportStatus']);
        Route::get('/report-pdf', [AcademicFinderPdfController::class, 'reportPdf']);
    });
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Admin routes
    Route::middleware('ability:admin')->prefix('admin')->group(function () {
        // Companies management
        Route::apiResource('companies', CompanyController::class)->only(['index', 'show', 'destroy']);
        Route::put('companies/{company}/enable', [CompanyController::class, 'enable']);
        Route::put('companies/{company}/disable', [CompanyController::class, 'disable']);

        // Invitations management
        Route::apiResource('invitations', AdminInvitationController::class)->only(['index', 'store', 'destroy']);
    });

    // Company routes
    Route::middleware('ability:company')->prefix('company')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
    });
});
