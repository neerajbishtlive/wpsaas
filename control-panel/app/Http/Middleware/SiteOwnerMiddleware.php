<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SiteOwnerMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->unauthorized($request, 'Authentication required');
        }

        // Extract site ID from route or request
        $siteId = $request->route('site') ?? 
                  $request->route('id') ?? 
                  $request->input('site_id');

        if (!$siteId) {
            return $this->unauthorized($request, 'Site ID required');
        }

        // Load site with minimal query
        $site = Site::select('id', 'user_id', 'status')
            ->find($siteId);

        if (!$site) {
            return $this->unauthorized($request, 'Site not found');
        }

        // Admin bypass
        if ($user->is_admin) {
            $request->merge(['site' => $site]);
            return $next($request);
        }

        // Ownership check
        if ($site->user_id !== $user->id) {
            activity()
                ->causedBy($user)
                ->performedOn($site)
                ->log('Unauthorized site access attempt');
                
            return $this->unauthorized($request, 'Access denied');
        }

        // Site status check for non-admins
        if (in_array($site->status, ['deleted', 'suspended']) && 
            !in_array($request->route()->getName(), ['site.billing', 'site.reactivate'])) {
            return $this->unauthorized($request, "Site is {$site->status}");
        }

        // Attach site to request
        $request->merge(['site' => $site]);

        return $next($request);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorized(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'error' => 'unauthorized'
            ], 403);
        }

        abort(403, $message);
    }
}