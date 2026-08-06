<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ShippingAddressController;
use App\Http\Controllers\Admin\WalletTransactionController;
use App\Http\Controllers\Admin\DiscountCouponController;
use App\Http\Controllers\Admin\CustomerActivityController;
use App\Http\Controllers\Admin\AdvertisementController as AdminAdController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// ==================== Public Routes ====================

// الصفحة الرئيسية للمتجر (واجهة العميل)
Route::get('/', function () {
    return view('welcome');
});

// مسارات المصادقة
require __DIR__ . '/auth.php';

// Google Authentication
Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleController::class, 'redirectToGoogle'])->name('google.login');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleController::class, 'handleGoogleCallback'])->name('google.callback');

// ==================== Shared Auth Routes ====================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // مسار لوحة التحكم العام الذي يوجه المستخدم حسب صلاحياته
    Route::get('/dashboard', function () {
        $user = auth()->user();
        if ($user->hasRole('super-admin')) {
            return redirect()->route('admin.dashboard');
        } elseif ($user->hasRole('vendor')) {
            return redirect()->route('vendor.dashboard');
        }
        return redirect('/');
    })->name('dashboard');
});

// ==================== Admin Routes ====================
Route::prefix('admin')
    ->middleware(['auth', 'role:super-admin'])
    ->name('admin.')
    ->group(function () {

        // 1. Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('categories', CategoryController::class);
        Route::patch('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggleStatus');
        Route::resource('brands', BrandController::class);


        // 2. Vendors Management
        Route::post('vendors/{vendor}/toggle-status', [VendorController::class, 'toggleStatus'])->name('vendors.toggleStatus');
        Route::post('vendors/{vendor}/ban', [VendorController::class, 'ban'])->name('vendors.ban');
        Route::post('vendors/{vendor}/activate', [VendorController::class, 'activate'])->name('vendors.activate');
        Route::resource('vendors', VendorController::class);

        // 3. Stores Management
        Route::post('stores/{store}/toggle-status', [StoreController::class, 'toggleStatus'])->name('stores.toggleStatus');
        Route::resource('stores', StoreController::class);

        // 4. Products Management
        // Use ['as' => 'admin'] to prefix resource route names with 'admin.' properly if needed,
        // but since we are wrapped in name('admin.'), a simple resource usually suffices.
        // However, let's correspond to previous fixes to ensure 'admin.products.index' exists.
        Route::resource('products', ProductController::class);
        Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggleStatus');

        // 5. Orders Management
        Route::get('orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])->name('orders.show');

        // 6. Returns Management (New)
        Route::get('returns', [\App\Http\Controllers\Admin\OrderReturnController::class, 'index'])->name('returns.index');
        Route::patch('returns/{returnOrder}', [\App\Http\Controllers\Admin\OrderReturnController::class, 'updateStatus'])->name('returns.updateStatus');
        Route::patch('returns/{returnOrder}/restock', [\App\Http\Controllers\Admin\OrderReturnController::class, 'restock'])->name('returns.restock');

        // 6.2 Shipping Management (New Demo)
        Route::get('shipping', [\App\Http\Controllers\Admin\ShippingController::class, 'index'])->name('shipping.index');
        Route::post('shipping', [\App\Http\Controllers\Admin\ShippingController::class, 'store'])->name('shipping.store');
        Route::patch('shipping/{shipping}/update-status', [\App\Http\Controllers\Admin\ShippingController::class, 'updateStatus'])->name('shipping.updateStatus');

        // 7. Users (Customers) Management
        Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.resetPassword');
        Route::post('users/{user}/send-otp', [UserController::class, 'sendOtp'])->name('users.sendOtp');
        Route::post('users/{user}/verify-account', [UserController::class, 'verifyAccount'])->name('users.verifyAccount');
        Route::resource('users', UserController::class);



        // 8. Financial Reports
        Route::get('reports/financial', [\App\Http\Controllers\Admin\FinancialReportController::class, 'index'])->name('reports.financial');

        // 9. Reviews
        Route::get('reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('reviews/{review}/update-status', [\App\Http\Controllers\Admin\ReviewController::class, 'updateStatus'])->name('reviews.updateStatus');

        // 10. Notifications
        Route::get('notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');

        // 11. Complaints
        Route::get('complaints', [\App\Http\Controllers\Admin\ComplaintController::class, 'index'])->name('complaints.index');

        // 12. Technical Support
        Route::get('support', [\App\Http\Controllers\Admin\SupportController::class, 'index'])->name('support.index');
        Route::post('support', [\App\Http\Controllers\Admin\SupportController::class, 'store'])->name('support.store');

        // Platform Settings
        Route::resource('settings', \App\Http\Controllers\Admin\SettingController::class)->only(['index', 'store']);

        // 13. Payment Gateways Management (New)
        Route::get('payment-gateways', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
        Route::post('payment-gateways', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'store'])->name('payment-gateways.store');
        Route::resource('electronic-wallets', \App\Http\Controllers\Admin\ElectronicWalletController::class);

        // 14. Advertisements Management (New)
        Route::get('advertisements', [AdminAdController::class, 'index'])->name('advertisements.index');
        Route::patch('advertisements/{advertisement}/approve', [AdminAdController::class, 'approve'])->name('advertisements.approve');
        Route::patch('advertisements/{advertisement}/reject', [AdminAdController::class, 'reject'])->name('advertisements.reject');
        Route::resource('advertisements', AdminAdController::class);

        // Activity Logs
        Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');

        // Shipping Addresses
        Route::get('shipping-addresses', [ShippingAddressController::class, 'index'])->name('shipping-addresses.index');
        Route::delete('shipping-addresses/{id}', [ShippingAddressController::class, 'destroy'])->name('shipping-addresses.destroy');

        // Wallet Transactions
        Route::get('wallet-transactions', [WalletTransactionController::class, 'index'])->name('wallet-transactions.index');
        Route::post('wallet-transactions', [WalletTransactionController::class, 'store'])->name('wallet-transactions.store');
        Route::delete('wallet-transactions/{walletTransaction}', [WalletTransactionController::class, 'destroy'])->name('wallet-transactions.destroy');

        // Discount Coupons
        Route::get('discount-coupons', [DiscountCouponController::class, 'index'])->name('discount-coupons.index');
        Route::post('discount-coupons', [DiscountCouponController::class, 'store'])->name('discount-coupons.store');
        Route::put('discount-coupons/{id}', [DiscountCouponController::class, 'update'])->name('discount-coupons.update');
        Route::delete('discount-coupons/{id}', [DiscountCouponController::class, 'destroy'])->name('discount-coupons.destroy');
        Route::patch('discount-coupons/{id}/toggle-status', [DiscountCouponController::class, 'toggleStatus'])->name('discount-coupons.toggleStatus');

        // Customer Activities
        Route::get('customer-activities', [CustomerActivityController::class, 'index'])->name('customer-activities.index');

        // Coming Soon (Temporary for new features)
        Route::get('coming-soon', function () {
            return view('admin.coming-soon');
        })->name('coming-soon');

        // Test Route
        Route::get('/test-notification', function () {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user) {
                $user->notify(new \App\Notifications\TestNotification('تجربة إشعار جديد في ' . now()->toTimeString()));
                return 'تم إرسال الإشعار بنجاح!';
            }
            return 'يجب تسجيل الدخول أولاً.';
        });
    });


// ==================== Vendor Routes ====================
Route::prefix('vendor')
    ->middleware(['auth', 'role:vendor'])
    ->name('vendor.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Vendor\DashboardController::class, 'index'])->name('dashboard');

        // Products
        Route::resource('products', \App\Http\Controllers\Vendor\ProductController::class);
        Route::patch('products/{product}/toggle-status', [\App\Http\Controllers\Vendor\ProductController::class, 'toggleStatus'])->name('products.toggleStatus');
        Route::post('products/{product}/re-review', [\App\Http\Controllers\Vendor\ProductController::class, 'reReview'])->name('products.reReview');

        // Orders
        Route::resource('orders', \App\Http\Controllers\Vendor\OrderController::class)->except(['edit', 'destroy']);
        Route::get('orders/create', [\App\Http\Controllers\Vendor\OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [\App\Http\Controllers\Vendor\OrderController::class, 'store'])->name('orders.store');

        // Warehouse
        Route::get('warehouse', [\App\Http\Controllers\Vendor\WarehouseController::class, 'index'])->name('warehouse.index');
        Route::get('warehouse/import', [\App\Http\Controllers\Vendor\WarehouseController::class, 'import'])->name('warehouse.import');
        Route::post('warehouse/upload-csv', [\App\Http\Controllers\Vendor\WarehouseController::class, 'uploadCsv'])->name('warehouse.upload-csv');
        Route::post('warehouse/process-import', [\App\Http\Controllers\Vendor\WarehouseController::class, 'processImport'])->name('warehouse.process-import');
        Route::get('warehouse/download-template', [\App\Http\Controllers\Vendor\WarehouseController::class, 'downloadTemplate'])->name('warehouse.download-template');
        Route::patch('warehouse/products/{product}/stock', [\App\Http\Controllers\Vendor\WarehouseController::class, 'updateStock'])->name('warehouse.updateStock');

        // Advertisements
        Route::resource('advertisements', \App\Http\Controllers\Vendor\AdvertisementController::class);
        Route::patch('advertisements/{advertisement}/toggle-status', [\App\Http\Controllers\Vendor\AdvertisementController::class, 'toggleStatus'])->name('advertisements.toggleStatus');

        // Discounts
        Route::resource('discounts', \App\Http\Controllers\Vendor\DiscountController::class);
        Route::patch('discounts/{discount}/toggle-status', [\App\Http\Controllers\Vendor\DiscountController::class, 'toggleStatus'])->name('discounts.toggleStatus');

        // Store Settings
        Route::get('store/edit', [\App\Http\Controllers\Vendor\DashboardController::class, 'editStore'])->name('store.edit');
        Route::put('store/update', [\App\Http\Controllers\Vendor\DashboardController::class, 'updateStore'])->name('store.update');
        Route::post('store/generate-token', [\App\Http\Controllers\Vendor\DashboardController::class, 'generateToken'])->name('store.generate-token');

        // Sales
        Route::get('sales', [\App\Http\Controllers\Vendor\SalesController::class, 'index'])->name('sales.index');
        Route::get('sales/export', [\App\Http\Controllers\Vendor\SalesController::class, 'export'])->name('sales.export');

        // Reviews
        Route::get('reviews', [\App\Http\Controllers\Vendor\ReviewController::class, 'index'])->name('reviews.index');
        Route::get('reviews/{review}', [\App\Http\Controllers\Vendor\ReviewController::class, 'show'])->name('reviews.show');
        Route::patch('reviews/{review}/update-status', [\App\Http\Controllers\Vendor\ReviewController::class, 'updateStatus'])->name('reviews.updateStatus');

        // Top Products
        Route::get('top-products', [\App\Http\Controllers\Vendor\TopProductsController::class, 'index'])->name('top-products.index');

        // Financial Reports
        Route::get('financial-reports', [\App\Http\Controllers\Vendor\FinancialReportController::class, 'index'])->name('financial-reports.index');

        // Shipping Info (New)
        Route::get('shipping-info', [\App\Http\Controllers\Vendor\ShippingController::class, 'index'])->name('shipping.index');

        // Chatbot Rules (New)
        Route::get('chatbot-rules', [\App\Http\Controllers\Vendor\ChatbotController::class, 'index'])->name('chatbot-rules.index');
        Route::post('chatbot-rules', [\App\Http\Controllers\Vendor\ChatbotController::class, 'store'])->name('chatbot-rules.store');
        Route::delete('chatbot-rules/{chatbot}', [\App\Http\Controllers\Vendor\ChatbotController::class, 'destroy'])->name('chatbot-rules.destroy');

        // Messages (New)
        Route::get('messages', [\App\Http\Controllers\Vendor\MessageController::class, 'index'])->name('messages.index');
    });
    Route::get('/fix-api', function() {
    \Artisan::call('route:clear');
    \Artisan::call('config:clear');
    \Artisan::call('cache:clear');
    return "تم تنظيف الكاش وتحديث المسارات بنجاح!";
});

require __DIR__ . '/db_fix.php';