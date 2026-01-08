<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Company\ProfileController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Invitation routes (public)
Route::prefix('invitations')->group(function () {
    Route::get('/validate/{token}', [InvitationController::class, 'validate']);
    Route::post('/accept/{token}', [InvitationController::class, 'accept']);
});

// Exam results routes (public)
Route::prefix('exam-results')->group(function () {
    Route::post('/process', [ExamResultController::class, 'process']);
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
