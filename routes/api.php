<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaypalController;
use App\Http\Controllers\Api\PaypalWebhookController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TutorController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// PayPal calls this route directly. There is no user login here,
// so it must stay OUTSIDE the auth:sanctum group.
Route::post(
    '/paypal/webhook',
    [PaypalWebhookController::class, 'webhook']
);


Route::middleware('auth:sanctum')->group(function () {

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );

    Route::get(
        '/tutors',
        [TutorController::class, 'index']
    );
    // Must be before /tutors/{id}
    Route::get(
        '/tutors/reservations',
        [TutorController::class, 'reservations']
    );

    Route::get(
        '/tutors/calendar',
        [TutorController::class, 'myCalendar']
    );

    Route::get(
        '/tutors/{id}',
        [TutorController::class, 'show']
    );

    Route::get(
        '/tutors/{id}/calendar',
        [TutorController::class, 'calendar']
    );



    // Student routes
    Route::get(
        '/my-reservations',
        [StudentController::class, 'myReservations']
    );

    Route::get(
        '/my-profile',
        [StudentController::class, 'myProfile']
    );

    Route::put(
        '/my-profile',
        [StudentController::class, 'updateMyProfile']
    );

    // change password route
    Route::put(
        '/my-profile/change-password',
        [StudentController::class, 'changePassword']
    );


    Route::get(
        '/my-payments',
        [StudentController::class, 'myPayments']
    );

    Route::post(
        '/paypal/create-order',
        [PaypalController::class, 'createOrder']
    );
});
