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

// ---- SEO --------------------------------------------------------------------
Route::get('sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap']);

// SEO-тэй хуудсууд: server талд title/OG/JSON-LD, мэдэгдэхгүй slug-т жинхэнэ 404
Route::get('b/{slug}', [\App\Http\Controllers\SeoController::class, 'business']);
Route::get('c/{slug}', [\App\Http\Controllers\SeoController::class, 'category']);

// ---- SPA --------------------------------------------------------------------
// All remaining routes are handled by the Vue router.
Route::get('/{any?}', [\App\Http\Controllers\SeoController::class, 'spa'])
    ->where('any', '^(?!api|webhooks|storage|up).*$')
    ->name('spa');
