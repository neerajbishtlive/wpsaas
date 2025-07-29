<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show the main dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get user's sites with pagination
        $sites = Site::where('user_id', $user->id)
            ->latest()
            ->paginate(10);
        
        // Get statistics
        $stats = [
            'total_sites' => Site::where('user_id', $user->id)->count(),
            'active_sites' => Site::where('user_id', $user->id)->where('status', 'active')->count(),
            'storage_used' => $this->calculateStorageUsed($user->id),
            'recent_sites' => Site::where('user_id', $user->id)
                ->latest()
                ->take(5)
                ->get()
        ];
        
        return view('dashboard', compact('sites', 'stats'));
    }
    
    /**
     * Show quick stats for dashboard widgets
     */
    public function quickStats()
    {
        $user = Auth::user();
        
        $data = [
            'sites_count' => Site::where('user_id', $user->id)->count(),
            'active_sites' => Site::where('user_id', $user->id)->where('status', 'active')->count(),
            'inactive_sites' => Site::where('user_id', $user->id)->where('status', 'inactive')->count(),
            'sites_limit' => $user->subscription->plan->site_limit ?? 5,
        ];
        
        return response()->json($data);
    }
    
    /**
     * Search across user's sites
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        $user = Auth::user();
        
        $sites = Site::where('user_id', $user->id)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('subdomain', 'like', "%{$query}%")
                  ->orWhere('title', 'like', "%{$query}%");
            })
            ->paginate(10);
        
        if ($request->ajax()) {
            return response()->json([
                'sites' => $sites,
                'html' => view('partials.sites-list', compact('sites'))->render()
            ]);
        }
        
        return view('dashboard', compact('sites'));
    }
    
    /**
     * Show system notifications
     */
    public function notifications()
    {
        $notifications = [
            'unread_count' => 0,
            'items' => []
        ];
        
        // Add system notifications here
        // For example: maintenance, updates, etc.
        
        return view('notifications', compact('notifications'));
    }
    
    /**
     * Calculate storage used by user's sites
     */
    private function calculateStorageUsed($userId)
    {
        $sites = Site::where('user_id', $userId)->pluck('subdomain');
        $totalSize = 0;
        
        foreach ($sites as $subdomain) {
            $sitePath = base_path("../sites/{$subdomain}/wp-content");
            if (is_dir($sitePath)) {
                $totalSize += $this->getDirectorySize($sitePath);
            }
        }
        
        // Convert to human readable format
        return $this->formatBytes($totalSize);
    }
    
    /**
     * Get directory size recursively
     */
    private function getDirectorySize($dir)
    {
        $size = 0;
        
        if (!is_dir($dir)) {
            return $size;
        }
        
        foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $path) {
            $size += is_file($path) ? filesize($path) : $this->getDirectorySize($path);
        }
        
        return $size;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}