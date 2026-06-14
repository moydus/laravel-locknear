<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\CompanyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/auth/logout', [AuthController::class, 'logout']);

// Public API routes (Astro site uses these)
Route::post('/leads', [LeadController::class, 'store']);
Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/{company}', [CompanyController::class, 'show']);

// Authenticated routes (firma paneli)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // Company management
    Route::apiResource('companies', CompanyController::class)->except(['index', 'show']);

    // Lead management for companies
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/{lead}', [LeadController::class, 'show']);
});
