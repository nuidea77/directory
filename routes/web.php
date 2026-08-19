<?php

use App\Http\Controllers\Webhooks\BylWebhookController;
use App\Http\Controllers\Webhooks\VerifyMnCallbackController;
use Illuminate\Support\Facades\Route;

// ---- Provider callbacks (no session, no CSRF) -------------------------------
Route::get('webhooks/verify-mn/{verification}', VerifyMnCallbackController::class)
    ->name('webhooks.verify-mn');

Route::post('webhooks/byl', BylWebhookController::class)
    ->name('webhooks.byl')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

// ---- SPA --------------------------------------------------------------------
// All remaining routes are handled by the Vue router.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|webhooks|storage|up).*$')
    ->name('spa');
