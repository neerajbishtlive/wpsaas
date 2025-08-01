<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicSiteController::class, 'index'])->name('home');
Route::get('/pricing', [PublicSiteController::class, 'pricing'])->name('pricing');
Route::post('/contact', [PublicSiteController::class, 'contact'])->name('contact.send')->middleware('throttle:5,60');

// Guest site creation
Route::post('/sites/create-guest', [PublicSiteController::class, 'createGuestSite'])
    ->name('sites.create-guest')
    ->middleware('throttle:rate_limit:site.create');

// Subdomain availability check
Route::get('/subdomain/check', [PublicSiteController::class, 'checkSubdomain'])
    ->name('subdomain.check')
    ->middleware('throttle:rate_limit:subdomain.check');

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
});

/*
|--------------------------------------------------------------------------
| Authenticated User Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [SiteController::class, 'dashboard'])->name('dashboard');
    
    // Sites Management
    Route::prefix('sites')->name('sites.')->group(function () {
        Route::get('/', [SiteController::class, 'index'])->name('index');
        Route::get('/create', [SiteController::class, 'create'])->name('create');
        Route::post('/', [SiteController::class, 'store'])->name('store')->middleware('throttle:rate_limit:site.create');
        
        // Site-specific routes with ownership check
        Route::middleware(['site.owner'])->group(function () {
            Route::get('/{site}/manage', [SiteController::class, 'manage'])->name('manage');
            Route::post('/{site}/backup', [SiteController::class, 'backup'])->name('backup')->middleware('throttle:rate_limit:backup.create');
            Route::post('/{site}/reactivate', [SiteController::class, 'reactivate'])->name('reactivate');
            Route::delete('/{site}', [SiteController::class, 'destroy'])->name('destroy');
            Route::post('/{site}/clone', [SiteController::class, 'clone'])->name('clone');
            Route::put('/{site}/settings', [SiteController::class, 'updateSettings'])->name('settings.update');
        });
    });
    
    // Billing & Subscriptions
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('index');
        Route::get('/plans', [BillingController::class, 'plans'])->name('plans');
        Route::post('/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('subscribe');
        Route::post('/cancel', [BillingController::class, 'cancel'])->name('cancel');
        Route::post('/resume', [BillingController::class, 'resume'])->name('resume');
        Route::post('/update-payment-method', [BillingController::class, 'updatePaymentMethod'])->name('payment.update');
        Route::get('/invoices/{invoice}', [BillingController::class, 'downloadInvoice'])->name('invoice.download');
    });
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Backups
    Route::get('/backups', [SiteController::class, 'backups'])->name('backups.index');
    Route::post('/backups/{backup}/restore', [SiteController::class, 'restoreBackup'])->name('backups.restore');
    Route::delete('/backups/{backup}', [SiteController::class, 'deleteBackup'])->name('backups.delete');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    
    // Sites Management
    Route::get('/sites', [AdminController::class, 'sites'])->name('sites');
    Route::post('/sites/{site}/suspend', [AdminController::class, 'suspendSite'])->name('sites.suspend');
    Route::post('/sites/{site}/unsuspend', [AdminController::class, 'unsuspendSite'])->name('sites.unsuspend');
    Route::delete('/sites/{site}', [AdminController::class, 'deleteSite'])->name('sites.delete');
    Route::get('/sites/{site}/login', [AdminController::class, 'loginToSite'])->name('sites.login');
    
    // Users Management
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/users/{user}', [AdminController::class, 'userDetails'])->name('users.show');
    Route::post('/users/{user}/suspend', [AdminController::class, 'suspendUser'])->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [AdminController::class, 'unsuspendUser'])->name('users.unsuspend');
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
    
    // Plans Management
    Route::get('/plans', [AdminController::class, 'plans'])->name('plans');
    Route::post('/plans', [AdminController::class, 'createPlan'])->name('plans.create');
    Route::put('/plans/{plan}', [AdminController::class, 'updatePlan'])->name('plans.update');
    Route::delete('/plans/{plan}', [AdminController::class, 'deletePlan'])->name('plans.delete');
    
    // System
    Route::get('/system', [AdminController::class, 'system'])->name('system');
    Route::post('/system/clear-cache', [AdminController::class, 'clearCache'])->name('system.clear-cache');
    Route::get('/system/logs', [AdminController::class, 'logs'])->name('system.logs');
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
    
    // Export
    Route::get('/export/users', [AdminController::class, 'exportUsers'])->name('export.users');
    Route::get('/export/revenue', [AdminController::class, 'exportRevenue'])->name('export.revenue');
});

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
*/

Route::post('/stripe/webhook', [BillingController::class, 'handleWebhook'])
    ->name('stripe.webhook')
    ->middleware('stripe.webhook');

/*
|--------------------------------------------------------------------------
| 2FA Routes (if enabled)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/2fa/verify', [TwoFactorController::class, 'show'])->name('2fa.verify');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);
});

/*
|--------------------------------------------------------------------------
| Logout Route
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| WordPress Site Handler - Redirect to standalone loader
|--------------------------------------------------------------------------
*/
Route::get('/wp/{subdomain}/{path?}', function ($subdomain, $path = '') {
    // Check if site exists first
    $sitePath = base_path('../sites/' . $subdomain);
    if (!file_exists($sitePath) || !file_exists($sitePath . '/wp-config.php')) {
        abort(404, 'WordPress site not found');
    }
    
    // Pass through to the standalone WordPress loader
    return response()->file(public_path('wp-loader.php'));
})->where('path', '.*')->name('wordpress.site');