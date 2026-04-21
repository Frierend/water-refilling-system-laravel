<?php

use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\MobileVerificationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function (Request $request) {
        Log::channel('security')->info('security.email.verification.notice.viewed', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        return view('auth.verify-email');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        Log::channel('security')->info('security.email.verification.fulfilled', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Email verified successfully.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        Log::channel('security')->info('security.email.verification.sent', [
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        return back()->with('status', 'Verification link sent.');
    })->middleware('throttle:6,1')->name('verification.send');

    Route::get('/mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaController::class, 'verifyChallenge'])->name('mfa.challenge.verify');

    Route::get('/mobile/verify', [MobileVerificationController::class, 'show'])->name('mobile.verification.notice');
    Route::post('/mobile/verify/send', [MobileVerificationController::class, 'sendOtp'])->name('mobile.verification.send');
    Route::post('/mobile/verify/confirm', [MobileVerificationController::class, 'verifyOtp'])->name('mobile.verification.verify');
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/force-password-change', [ForcedPasswordChangeController::class, 'show'])
        ->name('password.force.change');
    Route::post('/force-password-change', [ForcedPasswordChangeController::class, 'update'])
        ->name('password.force.update');
});

Route::middleware(['auth', 'password.changed', 'verified', 'mfa'])->group(function () {
    Route::get('/mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/mfa/enable', [MfaController::class, 'enable'])->name('mfa.enable');
    Route::post('/mfa/disable', [MfaController::class, 'disable'])->name('mfa.disable');

    Route::get('/users/create', [UserManagementController::class, 'create'])
        ->middleware('role:owner')
        ->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])
        ->middleware('role:owner')
        ->name('users.store');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('customers', CustomerController::class);

    Route::prefix('api')->group(function () {
        Route::get('/customers/search', [CustomerController::class, 'search']);
    });

    Route::resource('orders', OrderController::class);
    Route::get('/walkin', [OrderController::class, 'createWalkin'])->name('orders.walkin');
    Route::post('/walkin', [OrderController::class, 'storeWalkin'])->name('orders.walkin.store');
    Route::post('/orders/{id}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('/deliveries/{order}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{id}/complete', [DeliveryController::class, 'complete'])->name('deliveries.complete');
    Route::post('/deliveries/{id}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
    Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
    Route::get('/inventory-low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{id}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::get('/inventory/{id}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{id}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{id}', [InventoryController::class, 'destroy'])->name('inventory.destroy');

    Route::get('/inventory/{id}/adjust', [InventoryController::class, 'showAdjustForm'])->name('inventory.adjust');
    Route::post('/inventory/adjust', [InventoryController::class, 'adjustStore'])->name('inventory.adjust.store');

    Route::get('/reports/sales', [ReportController::class, 'salesReport'])->name('reports.sales');
    Route::get('/reports/delivery', [ReportController::class, 'deliveryReport'])->name('reports.delivery');
    Route::get('/reports/customer', [ReportController::class, 'customerReport'])->name('reports.customer');
    Route::get('/reports/inventory', [ReportController::class, 'inventoryReport'])->name('reports.inventory');

    Route::get('/reports/sales/export', [ReportController::class, 'exportSalesReport'])->name('reports.sales.export');
    Route::get('/reports/delivery/export', [ReportController::class, 'exportDeliveryReport'])->name('reports.delivery.export');
    Route::get('/reports/customer/export', [ReportController::class, 'exportCustomerReport'])->name('reports.customer.export');
    Route::get('/reports/inventory/export', [ReportController::class, 'exportInventoryReport'])->name('reports.inventory.export');
});
