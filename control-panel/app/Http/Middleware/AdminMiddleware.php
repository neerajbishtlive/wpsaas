<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role = 'admin'): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated. Please log in.',
                    'error' => 'authentication_required'
                ], 401);
            }
            
            return redirect()->route('login')
                ->with('error', 'Please log in to access this area.');
        }

        $user = Auth::user();

        // Check if user has admin role
        if (!$this->hasAdminAccess($user, $role)) {
            // Log unauthorized access attempt
            Log::warning('Unauthorized admin access attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'requested_url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'required_role' => $role
            ]);

            // Record security event
            activity()
                ->causedBy($user)
                ->withProperties([
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'role_required' => $role
                ])
                ->log('Unauthorized admin access attempt');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to access this resource.',
                    'error' => 'insufficient_permissions'
                ], 403);
            }

            abort(403, 'Unauthorized. You do not have admin privileges.');
        }

        // Check if admin account is active
        if ($user->is_admin && $user->admin_status === 'suspended') {
            Auth::logout();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your admin account has been suspended.',
                    'error' => 'account_suspended'
                ], 403);
            }
            
            return redirect()->route('login')
                ->with('error', 'Your admin account has been suspended. Please contact the system administrator.');
        }

        // Check for IP restrictions if configured
        if ($this->hasIpRestrictions() && !$this->isAllowedIp($request->ip())) {
            Log::warning('Admin access from unauthorized IP', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied from this IP address.',
                    'error' => 'ip_not_allowed'
                ], 403);
            }

            abort(403, 'Access denied from this IP address.');
        }

        // Check for two-factor authentication if enabled
        if ($this->requiresTwoFactor($user) && !$request->session()->get('2fa_verified')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Two-factor authentication required.',
                    'error' => '2fa_required',
                    'redirect' => route('2fa.verify')
                ], 403);
            }

            return redirect()->route('2fa.verify')
                ->with('info', 'Please complete two-factor authentication to access admin area.');
        }

        // Update last admin activity
        $this->updateLastActivity($user);

        // Add admin context to request
        $request->merge(['admin_user' => $user]);

        return $next($request);
    }

    /**
     * Check if user has admin access
     */
    protected function hasAdminAccess($user, string $requiredRole): bool
    {
        // Check basic admin flag
        if (!$user->is_admin) {
            return false;
        }

        // If no specific role required, basic admin is enough
        if ($requiredRole === 'admin') {
            return true;
        }

        // Check for specific admin roles
        $adminRoles = [
            'super_admin' => 3,
            'admin' => 2,
            'support' => 1
        ];

        $userLevel = $adminRoles[$user->admin_role] ?? 0;
        $requiredLevel = $adminRoles[$requiredRole] ?? 2;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Check if IP restrictions are enabled
     */
    protected function hasIpRestrictions(): bool
    {
        return config('app.admin_ip_restriction', false) && 
               !empty(config('app.admin_allowed_ips', []));
    }

    /**
     * Check if IP is allowed
     */
    protected function isAllowedIp(string $ip): bool
    {
        $allowedIps = config('app.admin_allowed_ips', []);
        
        // Check exact match
        if (in_array($ip, $allowedIps)) {
            return true;
        }

        // Check CIDR ranges
        foreach ($allowedIps as $allowed) {
            if (strpos($allowed, '/') !== false) {
                if ($this->ipInRange($ip, $allowed)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    protected function ipInRange(string $ip, string $cidr): bool
    {
        list($subnet, $bits) = explode('/', $cidr);
        if ($bits === null) {
            $bits = 32;
        }

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;

        return ($ip & $mask) == $subnet;
    }

    /**
     * Check if two-factor authentication is required
     */
    protected function requiresTwoFactor($user): bool
    {
        // Global 2FA requirement for admins
        if (config('app.admin_require_2fa', true)) {
            return true;
        }

        // User-specific 2FA setting
        return $user->two_factor_enabled ?? false;
    }

    /**
     * Update last admin activity timestamp
     */
    protected function updateLastActivity($user): void
    {
        // Update in cache for real-time tracking
        cache()->put(
            "admin_activity_{$user->id}",
            now(),
            now()->addMinutes(30)
        );

        // Update in database every 5 minutes to reduce writes
        $lastUpdate = cache()->get("admin_db_update_{$user->id}");
        
        if (!$lastUpdate || $lastUpdate->diffInMinutes(now()) > 5) {
            $user->update(['last_admin_activity_at' => now()]);
            cache()->put("admin_db_update_{$user->id}", now(), now()->addHours(1));
        }
    }
}