<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TutorController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/tutors', [TutorController::class, 'index']);
    // Must be before /tutors/{id}
    Route::get('/tutors/reservations', [TutorController::class, 'reservations']);

    Route::get('/tutors/calendar', [TutorController::class, 'myCalendar']);

    Route::get('/tutors/{id}', [TutorController::class, 'show']);

    Route::get('/tutors/{id}/calendar', [TutorController::class, 'calendar']);
});
