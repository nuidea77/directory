<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\Console\BranchController;
use App\Http\Controllers\Api\V1\Console\ConsoleReviewController;
use App\Http\Controllers\Api\V1\Console\OrganizationController;
use App\Http\Controllers\Api\V1\Console\StatsController;
use App\Http\Controllers\Api\V1\CorrectionController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — Vue SPA болон мобайл апп хоёулаа энэ API-г хэрэглэнэ
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // ---- Auth ----------------------------------------------------------
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/login-sms', [AuthController::class, 'loginBySms']);
        Route::post('auth/reset', [AuthController::class, 'resetStart']);
        Route::post('auth/reset/confirm', [AuthController::class, 'resetConfirm']);
    });

    Route::get('auth/verifications/{uuid}', [AuthController::class, 'verificationStatus'])
        ->middleware('throttle:30,1');

    // ---- Нийтийн лавлах ---------------------------------------------------
    Route::get('home', [DirectoryController::class, 'home']);
    Route::get('search', [DirectoryController::class, 'search']);
    Route::get('locations', [DirectoryController::class, 'locations']);
    Route::get('amenities', [DirectoryController::class, 'amenities']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);
    Route::get('businesses/{slug}', [DirectoryController::class, 'business']);
    Route::post('branches/{branch}/event', [DirectoryController::class, 'event'])->middleware('throttle:120,1');
    Route::get('pricing', [PricingController::class, 'index']);

    // ---- Нэвтэрсэн хэрэглэгч ------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('auth/verify/start', [AuthController::class, 'startVerification'])->middleware('throttle:10,1');
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'updateProfile']);
        Route::put('me/password', [AuthController::class, 'changePassword']);

        // Хадгалсан
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('businesses/{business}/favorite', [FavoriteController::class, 'toggle']);

        // Миний сэтгэгдлүүд
        Route::get('my/reviews', [ReviewController::class, 'mine']);

        // Утас баталгаажсан хэрэглэгчийн үйлдлүүд
        Route::middleware('phone.verified')->group(function () {
            Route::post('branches/{branch}/reviews', [ReviewController::class, 'store'])->middleware('throttle:writes');
            Route::delete('branches/{branch}/reviews', [ReviewController::class, 'destroy']);
            Route::post('branches/{branch}/reviews/{review}/report', [ReviewController::class, 'report'])->middleware('throttle:writes');
            Route::post('reviews/{review}/helpful', [ReviewController::class, 'helpful'])->middleware('throttle:writes');
            Route::post('branches/{branch}/corrections', [CorrectionController::class, 'store'])->middleware('throttle:writes');

            // ---- Бизнес зөвлөл ------------------------------------------
            Route::get('console/organizations', [OrganizationController::class, 'index']);
            Route::post('console/organizations', [OrganizationController::class, 'store']);
            Route::put('console/organizations/{organization}', [OrganizationController::class, 'updateOrganization']);
            Route::post('console/businesses/{business}', [OrganizationController::class, 'updateBusiness']); // POST multipart
            Route::delete('console/businesses/{business}', [OrganizationController::class, 'destroyBusiness']);

            Route::post('console/businesses/{business}/branches', [BranchController::class, 'store']);
            Route::get('console/branches/{branch}', [BranchController::class, 'show']);
            Route::put('console/branches/{branch}', [BranchController::class, 'update']);
            Route::delete('console/branches/{branch}', [BranchController::class, 'destroy']);
            Route::post('console/branches/{branch}/images', [BranchController::class, 'addImages'])->middleware('throttle:writes');
            Route::delete('console/branches/{branch}/images/{image}', [BranchController::class, 'deleteImage']);
            Route::post('console/branches/{branch}/images/{image}/cover', [BranchController::class, 'setCover']);

            Route::get('console/organizations/{organization}/stats', [StatsController::class, 'show']);
            Route::get('console/organizations/{organization}/reviews', [ConsoleReviewController::class, 'index']);
            Route::post('console/reviews/{review}/reply', [ConsoleReviewController::class, 'reply']);

            // ---- Төлбөр, сурталчилгаа --------------------------------------
            Route::get('orders', [CheckoutController::class, 'index']);
            Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:writes');
            // Промо кодыг урьдчилан шалгах (спамаас хамгаалж хязгаартай)
            Route::post('checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:20,1');
            Route::get('orders/{order}', [CheckoutController::class, 'show']);
            Route::delete('orders/{order}', [CheckoutController::class, 'cancel']);
            Route::get('slots', [CheckoutController::class, 'slots']);
            Route::get('console/organizations/{organization}/campaigns', [CheckoutController::class, 'campaigns']);
        });

        // ---- Админ ---------------------------------------------------------
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('moderation', [AdminController::class, 'moderation']);
            Route::post('branches/{branch}/approve', [AdminController::class, 'approveBranch']);
            Route::post('branches/{branch}/reject', [AdminController::class, 'rejectBranch']);
            Route::get('revenue', [AdminController::class, 'revenue']);
            Route::get('businesses', [AdminController::class, 'businesses']);
            Route::get('reviews', [AdminController::class, 'reviews']);
            Route::post('reviews/{review}/moderate', [AdminController::class, 'moderateReview']);
            Route::get('corrections', [AdminController::class, 'corrections']);
            Route::post('corrections/{correction}/moderate', [AdminController::class, 'moderateCorrection']);
            Route::get('plans', [AdminController::class, 'plans']);
            Route::post('plans', [AdminController::class, 'storePlan']);
            Route::put('plans/{plan}', [AdminController::class, 'updatePlan']);
            Route::post('categories', [AdminController::class, 'storeCategory']);
            Route::put('categories/{category}', [AdminController::class, 'updateCategory']);
            Route::delete('categories/{category}', [AdminController::class, 'destroyCategory']);
            Route::get('search-aliases', [AdminController::class, 'searchAliases']);
            Route::post('search-aliases', [AdminController::class, 'storeSearchAlias']);
            Route::delete('search-aliases/{searchAlias}', [AdminController::class, 'destroySearchAlias']);
            Route::get('campaigns', [AdminController::class, 'campaigns']);
            Route::get('promo-codes', [AdminController::class, 'promoCodes']);
            Route::post('promo-codes', [AdminController::class, 'storePromoCode']);
            Route::put('promo-codes/{promoCode}', [AdminController::class, 'updatePromoCode']);
            Route::delete('promo-codes/{promoCode}', [AdminController::class, 'destroyPromoCode']);
        });
    });
});
