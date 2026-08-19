<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\MyListingController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — shared by the Vue SPA and the future mobile app
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // ---- Auth (public) -----------------------------------------------------
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/register', [AuthController::class, 'registerStart']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/reset', [AuthController::class, 'resetStart']);
        Route::post('auth/reset/confirm', [AuthController::class, 'resetConfirm']);
    });

    // Polling endpoint for verify.mn sessions (>= 3s interval on the client).
    Route::get('auth/verifications/{uuid}', [AuthController::class, 'verificationStatus'])
        ->middleware('throttle:30,1');

    // ---- Public directory ---------------------------------------------------
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);
    Route::get('listings', [ListingController::class, 'index']);
    Route::get('listings/featured', [ListingController::class, 'featured']);
    Route::get('listings/{slug}', [ListingController::class, 'show']);
    Route::get('plans', [PaymentController::class, 'plans']);

    // ---- Authenticated -------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'updateProfile']);
        Route::put('me/password', [AuthController::class, 'changePassword']);

        // Own listings
        Route::get('my/listings', [MyListingController::class, 'index']);
        Route::post('my/listings', [MyListingController::class, 'store']);
        Route::get('my/listings/{listing}', [MyListingController::class, 'show']);
        Route::post('my/listings/{listing}', [MyListingController::class, 'update']); // POST for multipart updates
        Route::delete('my/listings/{listing}', [MyListingController::class, 'destroy']);
        Route::post('my/listings/{listing}/images', [MyListingController::class, 'addImages']);
        Route::delete('my/listings/{listing}/images/{image}', [MyListingController::class, 'deleteImage']);

        // Reviews & favorites
        Route::post('listings/{listing}/reviews', [ReviewController::class, 'store']);
        Route::delete('listings/{listing}/reviews', [ReviewController::class, 'destroy']);
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('listings/{listing}/favorite', [FavoriteController::class, 'toggle']);

        // Featured-listing payments (byl.mn)
        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('my/listings/{listing}/feature', [PaymentController::class, 'store']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
    });
});
