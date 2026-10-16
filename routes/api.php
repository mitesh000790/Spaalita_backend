<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\BookingPolicyController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\MonthlySpecialController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserAuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefix: /api)
|--------------------------------------------------------------------------
|
| auth.jwt          - requires "Authorization: Bearer <token>"
| auth.jwt.optional - identifies the caller when a valid token is sent
| admin             - requires an admin token (use after auth.jwt)
| upload:{folder},{field}[,{max}] - accepts image uploads (jpg, png, gif)
|
*/

Route::prefix('admin')->controller(AdminAuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('logout', 'logout');
});

Route::prefix('users')->group(function () {
    Route::controller(UserAuthController::class)->group(function () {
        Route::post('login', 'login');
        Route::post('logout', 'logout');
        Route::post('forgot-password', 'forgotPassword');
        Route::post('reset-password/{token}', 'resetPassword');
    });

    Route::middleware('auth.jwt')->controller(UserController::class)->group(function () {
        Route::post('/', 'store')->middleware(['admin', 'upload:userProfile,profilePicture']);
        // Admins manage every account; customers only their own (checked in the controller).
        Route::put('{id}', 'update')->middleware('upload:userProfile,profilePicture');
        Route::put('{id}/addresses', 'updateAddresses');
        Route::delete('{id}', 'destroy')->middleware('admin');
        Route::get('/', 'index')->middleware('admin');
        Route::get('{id}', 'show');
    });
});

Route::prefix('products')->controller(ProductController::class)->group(function () {
    Route::post('/', 'store')->middleware(['auth.jwt', 'admin', 'upload:products,productImages,10']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin', 'upload:products,productImages,10']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
    Route::get('/', 'index');
    Route::get('{id}', 'show');
    Route::get('{id}/related', 'related');
    Route::post('apply-coupon', 'applyCoupon');
});

Route::prefix('giftcards')->controller(GiftCardController::class)->group(function () {
    Route::post('/', 'store')->middleware(['auth.jwt', 'admin', 'upload:products,productImages,10']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin', 'upload:products,productImages,10']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
    Route::get('/', 'index');
    Route::get('{id}', 'show');
    Route::get('{id}/related', 'related');
});

Route::prefix('services')->controller(ServiceController::class)->group(function () {
    Route::post('/', 'store')->middleware(['auth.jwt', 'admin', 'upload:services,serviceImage']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin', 'upload:services,serviceImage']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
    Route::get('/', 'index');
    Route::get('{id}', 'show');
});

Route::prefix('booking-policies')->controller(BookingPolicyController::class)->group(function () {
    Route::post('/', 'store')->middleware(['auth.jwt', 'admin']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
    Route::get('/', 'index');
    Route::get('{id}', 'show');
});

Route::prefix('monthly-special')->controller(MonthlySpecialController::class)->group(function () {
    Route::post('/', 'store')->middleware(['auth.jwt', 'admin', 'upload:monthlySpecial,image']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin', 'upload:monthlySpecial,image']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
    Route::get('/', 'index');
    Route::get('{id}', 'show');
});

Route::prefix('roles')->middleware(['auth.jwt', 'admin'])->controller(RoleController::class)->group(function () {
    Route::post('/', 'store');
    Route::put('{id}', 'update');
    Route::delete('{id}', 'destroy');
    Route::get('/', 'index');
    Route::get('{id}', 'show');
});

Route::prefix('orders')->controller(OrderController::class)->group(function () {
    Route::post('/', 'store')->middleware('auth.jwt.optional');
    // Admins see every order; customers only their own (filtered in the controller).
    Route::get('/', 'index')->middleware('auth.jwt');
    Route::put('{id}/status', 'updateStatus')->middleware(['auth.jwt', 'admin']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
    Route::post('webhook', 'stripeWebhook');
});

Route::post('payment/create-payment-intent', [PaymentController::class, 'createPaymentIntent']);

Route::post('contact', [ContactController::class, 'store']);

Route::prefix('newsletter')->controller(NewsletterController::class)->group(function () {
    Route::post('subscribe', 'subscribe');
    Route::post('unsubscribe', 'unsubscribe');
    Route::get('/', 'index')->middleware(['auth.jwt', 'admin']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
});

Route::prefix('gallery')->controller(GalleryController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('create', 'store')->middleware(['auth.jwt', 'admin', 'upload:gallery,images,10']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin', 'upload:gallery,image']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
});

Route::prefix('coupon')->controller(CouponController::class)->group(function () {
    Route::post('/', 'store')->middleware(['auth.jwt', 'admin']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin']);
    Route::get('{id}', 'show')->middleware(['auth.jwt', 'admin']);
    Route::get('/', 'index')->middleware(['auth.jwt', 'admin']);
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
    Route::post('apply', 'apply');
});

Route::prefix('category')->controller(CategoryController::class)->group(function () {
    Route::post('/', 'store')->middleware(['auth.jwt', 'admin', 'upload:categories,image']);
    Route::put('{id}', 'update')->middleware(['auth.jwt', 'admin', 'upload:categories,image']);
    Route::get('/', 'index');
    Route::get('{id}', 'show');
    Route::delete('{id}', 'destroy')->middleware(['auth.jwt', 'admin']);
});

Route::get('dashboard', DashboardController::class)->middleware(['auth.jwt', 'admin']);
