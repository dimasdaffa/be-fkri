<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\SubbagController;
use App\Http\Controllers\Api\KabidController;
use App\Http\Controllers\Api\KepalaController;

// Endpoint untuk Autentikasi & Registrasi (Publik)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Endpoint yang memerlukan autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile (untuk semua role)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // Fitur Pengusul
    Route::prefix('pengusul')->middleware('role:pengusul')->group(function () {
        Route::get('/proposals', [ProposalController::class, 'index']);
        Route::post('/proposals', [ProposalController::class, 'store']);
        Route::get('/proposals/{id}', [ProposalController::class, 'show']);
        Route::put('/proposals/{id}', [ProposalController::class, 'update']);
        Route::delete('/proposals/{id}', [ProposalController::class, 'destroy']);
    });

    // Fitur Subbag Program
    Route::prefix('subbag')->middleware('role:subbag_program')->group(function () {
        Route::get('/proposals', [SubbagController::class, 'index']);
        Route::put('/proposals/{id}/assess', [SubbagController::class, 'assess']); // Memberi nilai & approve/reject
    });

    // Fitur Kabid KRPI
    Route::prefix('kabid')->middleware('role:kabid_krpi')->group(function () {
        Route::get('/proposals', [KabidController::class, 'index']);
        Route::put('/proposals/{id}/decide', [KabidController::class, 'decide']); // Approve/reject
    });

    // Fitur Kepala BRIDA
    Route::prefix('kepala')->middleware('role:kepala_brida')->group(function () {
        Route::get('/proposals', [KepalaController::class, 'index']);
        Route::put('/proposals/{id}/decide', [KepalaController::class, 'decide']); // Approve/reject final
    });
});
