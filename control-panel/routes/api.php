<?php

use App\Http\Controllers\Api\SiteApiController;
use App\Http\Controllers\Api\StatsApiController;
use App\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public API endpoints
Route::prefix('v1')->group(function () {
    // Subdomain availability check
    Route::get('/subdomain/check', [PublicSiteController::class, 'checkSubdomainApi'])
        ->middleware(['throttle:rate_limit:subdomain.check']);
    
    // Public stats
    Route::get('/stats', [StatsApiController::class, 'public'])
        ->middleware(['throttle:60,1']);
});

// Authenticated API endpoints
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    // Sites API
    Route::apiResource('sites', SiteApiController::class);
    
    // Site-specific operations
    Route::prefix('sites/{site}')->middleware(['site.owner'])->group(function () {
        Route::post('/backup', [SiteApiController::class, 'backup']);
        Route::post('/restore', [SiteApiController::class, 'restore']);
        Route::post('/clone', [SiteApiController::class, 'clone']);
        Route::get('/stats', [SiteApiController::class, 'stats']);
        Route::post('/restart-php', [SiteApiController::class, 'restartPhp']);
        Route::post('/clear-cache', [SiteApiController::class, 'clearCache']);
        Route::get('/logs', [SiteApiController::class, 'logs']);
        Route::get('/files', [SiteApiController::class, 'files']);
        Route::post('/upload', [SiteApiController::class, 'upload']);
    });
    
    // User stats
    Route::get('/user/stats', [StatsApiController::class, 'user']);
    Route::get('/user/usage', [StatsApiController::class, 'usage']);
    
    // Billing API
    Route::get('/billing/subscription', [BillingApiController::class, 'subscription']);
    Route::get('/billing/invoices', [BillingApiController::class, 'invoices']);
    Route::get('/billing/payment-methods', [BillingApiController::class, 'paymentMethods']);
});

// Admin API endpoints
Route::middleware(['auth:sanctum', 'admin'])->prefix('v1/admin')->group(function () {
    // System stats
    Route::get('/stats/overview', [StatsApiController::class, 'overview']);
    Route::get('/stats/revenue', [StatsApiController::class, 'revenue']);
    Route::get('/stats/growth', [StatsApiController::class, 'growth']);
    Route::get('/stats/resources', [StatsApiController::class, 'resources']);
    
    // Bulk operations
    Route::post('/sites/bulk-suspend', [SiteApiController::class, 'bulkSuspend']);
    Route::post('/sites/bulk-delete', [SiteApiController::class, 'bulkDelete']);
    Route::post('/users/bulk-email', [UserApiController::class, 'bulkEmail']);
    
    // Real-time monitoring
    Route::get('/monitor/sites', [MonitorApiController::class, 'sites']);
    Route::get('/monitor/server', [MonitorApiController::class, 'server']);
    Route::get('/monitor/queue', [MonitorApiController::class, 'queue']);
});

// Webhook endpoints (no auth required)
Route::post('/webhooks/stripe', [BillingController::class, 'handleWebhook'])
    ->middleware(['stripe.webhook']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'up' : 'down',
            'redis' => Redis::ping() ? 'up' : 'down',
            'queue' => Queue::size() < 1000 ? 'healthy' : 'congested',
        ]
    ]);
});