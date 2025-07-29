<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Rate limit configurations per tier
     */
    protected array $rateLimits = [
        'guest' => [
            'per_minute' => 10,
            'per_hour' => 50,
            'per_day' => 100,
            'burst' => 5
        ],
        'free' => [
            'per_minute' => 30,
            'per_hour' => 500,
            'per_day' => 2000,
            'burst' => 10
        ],
        'starter' => [
            'per_minute' => 60,
            'per_hour' => 1000,
            'per_day' => 10000,
            'burst' => 20
        ],
        'pro' => [
            'per_minute' => 120,
            'per_hour' => 3000,
            'per_day' => 50000,
            'burst' => 30
        ],
        'business' => [
            'per_minute' => 300,
            'per_hour' => 10000,
            'per_day' => 200000,
            'burst' => 50
        ],
        'admin' => [
            'per_minute' => 1000,
            'per_hour' => 50000,
            'per_day' => 1000000,
            'burst' => 100
        ]
    ];

    /**
     * Endpoint-specific rate limits
     */
    protected array $endpointLimits = [
        'site.create' => ['per_hour' => 5, 'per_day' => 10],
        'backup.create' => ['per_hour' => 3, 'per_day' => 20],
        'password.reset' => ['per_hour' => 3, 'per_day' => 10],
        'contact.send' => ['per_hour' => 5, 'per_day' => 20],
        'subdomain.check' => ['per_minute' => 30, 'per_hour' => 100]
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $endpoint = null): Response
    {
        $identifier = $this->resolveRequestIdentifier($request);
        $tier = $this->resolveUserTier($request);
        
        // Check endpoint-specific limits first
        if ($endpoint && isset($this->endpointLimits[$endpoint])) {
            $endpointResponse = $this->checkEndpointLimit($request, $identifier, $endpoint);
            if ($endpointResponse) {
                return $endpointResponse;
            }
        }

        // Check general rate limits
        $limits = $this->rateLimits[$tier] ?? $this->rateLimits['guest'];
        
        // Check burst limit (short-term spike protection)
        $burstKey = "rate_limit:burst:{$identifier}";
        if (!$this->checkBurstLimit($burstKey, $limits['burst'])) {
            return $this->buildRateLimitResponse($request, 'burst', $limits['burst'], 10);
        }

        // Check per-minute limit
        $minuteKey = "rate_limit:minute:{$identifier}";
        if (!$this->checkLimit($minuteKey, $limits['per_minute'], 60)) {
            return $this->buildRateLimitResponse($request, 'minute', $limits['per_minute'], 60);
        }

        // Check per-hour limit
        $hourKey = "rate_limit:hour:{$identifier}";
        if (!$this->checkLimit($hourKey, $limits['per_hour'], 3600)) {
            return $this->buildRateLimitResponse($request, 'hour', $limits['per_hour'], 3600);
        }

        // Check per-day limit
        $dayKey = "rate_limit:day:{$identifier}";
        if (!$this->checkLimit($dayKey, $limits['per_day'], 86400)) {
            return $this->buildRateLimitResponse($request, 'day', $limits['per_day'], 86400);
        }

        // Track API usage for analytics
        $this->trackApiUsage($request, $identifier, $tier);

        // Add rate limit headers to response
        $response = $next($request);
        
        return $this->addRateLimitHeaders($response, $identifier, $limits);
    }

    /**
     * Resolve the request identifier
     */
    protected function resolveRequestIdentifier(Request $request): string
    {
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        // For guests, use IP + User Agent hash
        return 'ip:' . $request->ip() . ':' . md5($request->userAgent() ?? 'no-agent');
    }

    /**
     * Resolve user tier
     */
    protected function resolveUserTier(Request $request): string
    {
        $user = $request->user();
        
        if (!$user) {
            return 'guest';
        }

        if ($user->is_admin) {
            return 'admin';
        }

        // Map plan IDs to tiers
        $planTiers = [
            1 => 'free',
            2 => 'starter',
            3 => 'pro',
            4 => 'business'
        ];

        return $planTiers[$user->plan_id] ?? 'free';
    }

    /**
     * Check burst limit (sliding window)
     */
    protected function checkBurstLimit(string $key, int $limit): bool
    {
        $window = 10; // 10 second window
        $now = microtime(true);
        
        // Get current window data
        $requests = Cache::get($key, []);
        
        // Remove old entries outside window
        $requests = array_filter($requests, function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // Check if under limit
        if (count($requests) >= $limit) {
            return false;
        }
        
        // Add current request
        $requests[] = $now;
        Cache::put($key, $requests, $window);
        
        return true;
    }

    /**
     * Check rate limit
     */
    protected function checkLimit(string $key, int $limit, int $decay): bool
    {
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $limit) {
            return false;
        }
        
        Cache::increment($key);
        
        // Set expiry only on first attempt
        if ($attempts === 0) {
            Cache::put($key, 1, $decay);
        }
        
        return true;
    }

    /**
     * Check endpoint-specific limits
     */
    protected function checkEndpointLimit(Request $request, string $identifier, string $endpoint): ?Response
    {
        $limits = $this->endpointLimits[$endpoint];
        
        foreach ($limits as $period => $limit) {
            $key = "rate_limit:endpoint:{$endpoint}:{$period}:{$identifier}";
            $decay = $this->getPeriodSeconds($period);
            
            if (!$this->checkLimit($key, $limit, $decay)) {
                Log::warning('Endpoint rate limit exceeded', [
                    'endpoint' => $endpoint,
                    'identifier' => $identifier,
                    'period' => $period,
                    'limit' => $limit
                ]);
                
                return $this->buildRateLimitResponse($request, $period, $limit, $decay, $endpoint);
            }
        }
        
        return null;
    }

    /**
     * Get period in seconds
     */
    protected function getPeriodSeconds(string $period): int
    {
        return match($period) {
            'per_minute' => 60,
            'per_hour' => 3600,
            'per_day' => 86400,
            default => 60
        };
    }

    /**
     * Build rate limit response
     */
    protected function buildRateLimitResponse(
        Request $request, 
        string $period, 
        int $limit, 
        int $retryAfter,
        ?string $endpoint = null
    ): Response {
        $message = $endpoint 
            ? "Rate limit exceeded for {$endpoint}. Limit: {$limit} per {$period}"
            : "Rate limit exceeded. Limit: {$limit} per {$period}";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'rate_limit_exceeded',
                'limit' => $limit,
                'period' => $period,
                'retry_after' => $retryAfter
            ], 429)
            ->withHeaders([
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => time() + $retryAfter,
                'Retry-After' => $retryAfter
            ]);
        }

        abort(429, $message, ['Retry-After' => $retryAfter]);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, string $identifier, array $limits): Response
    {
        $minuteKey = "rate_limit:minute:{$identifier}";
        $attempts = Cache::get($minuteKey, 0);
        $remaining = max(0, $limits['per_minute'] - $attempts);
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => $limits['per_minute'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => time() + 60
        ]);
    }

    /**
     * Track API usage for analytics
     */
    protected function trackApiUsage(Request $request, string $identifier, string $tier): void
    {
        // Increment counters for analytics
        $date = now()->format('Y-m-d');
        $hour = now()->format('H');
        
        // Daily stats
        Cache::increment("api_usage:{$date}:{$tier}");
        Cache::increment("api_usage:{$date}:{$tier}:{$hour}");
        
        // Track unique users
        $uniqueKey = "api_unique:{$date}";
        $uniques = Cache::get($uniqueKey, []);
        if (!in_array($identifier, $uniques)) {
            $uniques[] = $identifier;
            Cache::put($uniqueKey, $uniques, 86400);
        }
        
        // Track endpoints
        $endpoint = $request->route()->getName() ?? 'unknown';
        Cache::increment("api_endpoint:{$date}:{$endpoint}");
        
        // Log suspicious activity
        if ($this->isSuspiciousActivity($identifier)) {
            Log::warning('Suspicious API activity detected', [
                'identifier' => $identifier,
                'endpoint' => $endpoint,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }
    }

    /**
     * Check for suspicious activity patterns
     */
    protected function isSuspiciousActivity(string $identifier): bool
    {
        // Check for rapid endpoint switching
        $endpoints = Cache::get("api_endpoints_accessed:{$identifier}", []);
        if (count($endpoints) > 20) {
            return true;
        }
        
        // Check for unusual access patterns
        $pattern = Cache::get("api_pattern:{$identifier}", []);
        if (count($pattern) > 0 && $this->detectAnomalousPattern($pattern)) {
            return true;
        }
        
        return false;
    }

    /**
     * Detect anomalous access patterns
     */
    protected function detectAnomalousPattern(array $pattern): bool
    {
        // Simple anomaly detection - can be enhanced with ML
        $intervals = [];
        for ($i = 1; $i < count($pattern); $i++) {
            $intervals[] = $pattern[$i] - $pattern[$i-1];
        }
        
        if (count($intervals) < 10) {
            return false;
        }
        
        // Check for bot-like regular intervals
        $avg = array_sum($intervals) / count($intervals);
        $variance = 0;
        foreach ($intervals as $interval) {
            $variance += pow($interval - $avg, 2);
        }
        $variance /= count($intervals);
        
        // Low variance suggests automated requests
        return $variance < 0.1;
    }
}