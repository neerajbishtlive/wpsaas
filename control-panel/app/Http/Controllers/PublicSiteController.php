<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Plan;
use App\Services\WordPressProvisioningService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class PublicSiteController extends Controller
{
    private $provisioningService;
    private $storageService;

    public function __construct(
        WordPressProvisioningService $provisioningService,
        StorageService $storageService
    ) {
        $this->provisioningService = $provisioningService;
        $this->storageService = $storageService;
    }

    /**
     * Show the main landing page
     */
    public function index()
    {
        $stats = [
            'total_sites' => Site::count(),
            'active_sites' => Site::where('status', 'active')->count(),
            'total_users' => \App\Models\User::count()
        ];

        $plans = Plan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('price')
            ->get();

        return view('public.index', compact('stats', 'plans'));
    }

    /**
     * Show quick start form
     */
    public function quickStart()
    {
        return view('public.quick-start');
    }

    /**
     * Create guest site (24-hour trial)
     */
    public function createGuestSite(Request $request)
    {
        // Rate limiting by IP
        $key = 'guest-site-creation:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Please try again in {$seconds} seconds."
            ], 429);
        }

        RateLimiter::hit($key, 3600); // 1 hour window

        $validator = Validator::make($request->all(), [
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9-]+$/',
                'unique:sites,subdomain'
            ],
            'title' => 'required|string|max:255',
            'admin_email' => 'required|email',
            'template' => 'nullable|string|in:blog,business,portfolio,ecommerce'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check subdomain availability (additional check)
            if ($this->isSubdomainTaken($request->subdomain)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This subdomain is already taken. Please choose another one.'
                ], 422);
            }

            // Generate secure admin credentials
            $adminUsername = 'admin_' . Str::random(8);
            $adminPassword = Str::random(16);

            // Create site record
            $site = Site::create([
                'user_id' => null, // Guest site
                'subdomain' => $request->subdomain,
                'title' => $request->title,
                'status' => 'provisioning',
                'plan_id' => null, // No plan for guest
                'expires_at' => now()->addHours(24), // 24-hour expiry
                'admin_username' => $adminUsername,
                'admin_email' => $request->admin_email,
                'template' => $request->template ?? 'blog'
            ]);

            // Create storage directories
            if (!$this->storageService->createSiteStorage($site)) {
                throw new Exception('Failed to create storage directories');
            }

            // Provision WordPress with template
            $provisionResult = $this->provisioningService->provisionSite($site, [
                'admin_username' => $adminUsername,
                'admin_password' => $adminPassword,
                'admin_email' => $request->admin_email,
                'site_title' => $request->title,
                'template' => $request->template ?? 'blog'
            ]);

            if (!$provisionResult['success']) {
                // Cleanup on failure
                $this->storageService->deleteSiteStorage($site);
                $site->delete();
                
                throw new Exception($provisionResult['message']);
            }

            // Update site status
            $site->update(['status' => 'active']);

            // Clear rate limit on success
            RateLimiter::clear($key);

            Log::info("Guest site created successfully", [
                'subdomain' => $site->subdomain,
                'ip' => $request->ip(),
                'template' => $request->template
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your WordPress site has been created successfully!',
                'site' => [
                    'id' => $site->id,
                    'subdomain' => $site->subdomain,
                    'url' => "https://{$site->subdomain}." . config('app.domain'),
                    'admin_url' => "https://{$site->subdomain}." . config('app.domain') . "/wp-admin",
                    'admin_username' => $adminUsername,
                    'admin_password' => $adminPassword,
                    'expires_at' => $site->expires_at->toISOString(),
                    'expires_in_hours' => 24
                ],
                'next_steps' => [
                    'login' => "Use the admin credentials to log into your WordPress dashboard",
                    'customize' => "Customize your site theme and add content",
                    'upgrade' => "Register an account to extend your site beyond 24 hours"
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Guest site creation failed: " . $e->getMessage(), [
                'subdomain' => $request->subdomain,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Site creation failed. Please try again with a different subdomain.'
            ], 500);
        }
    }

    /**
     * Check subdomain availability
     */
    public function checkSubdomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9-]+$/'
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => 'Invalid subdomain format. Use only lowercase letters, numbers, and hyphens (3-30 characters).'
            ]);
        }

        $subdomain = $request->subdomain;
        $isAvailable = !$this->isSubdomainTaken($subdomain);

        if ($isAvailable) {
            return response()->json([
                'available' => true,
                'message' => 'Subdomain is available!',
                'preview_url' => "https://{$subdomain}." . config('app.domain')
            ]);
        } else {
            return response()->json([
                'available' => false,
                'message' => 'This subdomain is already taken. Try another one.',
                'suggestions' => $this->generateSubdomainSuggestions($subdomain)
            ]);
        }
    }

    /**
     * Show pricing page
     */
    public function pricing()
    {
        $plans = Plan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('price')
            ->get();

        $features = [
            'guest' => [
                'sites' => 1,
                'duration' => '24 hours',
                'storage' => '100 MB',
                'bandwidth' => '1 GB',
                'custom_domain' => false,
                'ssl' => true,
                'backups' => false,
                'support' => 'Community'
            ]
        ];

        return view('public.pricing', compact('plans', 'features'));
    }

    /**
     * Show templates/themes page
     */
    public function templates()
    {
        $templates = [
            'blog' => [
                'name' => 'Blog',
                'description' => 'Perfect for personal blogs and content sites',
                'preview' => '/images/templates/blog-preview.jpg',
                'features' => ['Clean typography', 'Comment system', 'Social sharing']
            ],
            'business' => [
                'name' => 'Business',
                'description' => 'Professional business website template',
                'preview' => '/images/templates/business-preview.jpg',
                'features' => ['Contact forms', 'Service pages', 'Team showcase']
            ],
            'portfolio' => [
                'name' => 'Portfolio',
                'description' => 'Showcase your creative work beautifully',
                'preview' => '/images/templates/portfolio-preview.jpg',
                'features' => ['Gallery layouts', 'Project showcase', 'Client testimonials']
            ],
            'ecommerce' => [
                'name' => 'E-commerce',
                'description' => 'Ready-to-sell online store template',
                'preview' => '/images/templates/ecommerce-preview.jpg',
                'features' => ['Product catalog', 'Shopping cart', 'Payment integration']
            ]
        ];

        return view('public.templates', compact('templates'));
    }

    /**
     * Show demo site
     */
    public function demo(Request $request)
    {
        $template = $request->get('template', 'blog');
        
        if (!in_array($template, ['blog', 'business', 'portfolio', 'ecommerce'])) {
            $template = 'blog';
        }

        return view('public.demo', compact('template'));
    }

    /**
     * Get site statistics for public display
     */
    public function stats()
    {
        // Only show general stats, no sensitive data
        $stats = [
            'total_sites_created' => Site::count(),
            'active_sites' => Site::where('status', 'active')->count(),
            'countries_served' => 50, // Static for now
            'uptime_percentage' => 99.9 // Static for now
        ];

        return response()->json($stats);
    }

    /**
     * Handle contact form
     */
    public function contact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Log contact form submission
            Log::info("Contact form submission", [
                'name' => $request->name,
                'email' => $request->email,
                'subject' => $request->subject,
                'ip' => $request->ip()
            ]);

            // TODO: Send email to admin
            // TODO: Add to CRM/support system

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message! We\'ll get back to you soon.'
            ]);

        } catch (Exception $e) {
            Log::error("Contact form error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Sorry, there was an error sending your message. Please try again.'
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function isSubdomainTaken(string $subdomain): bool
    {
        // Check database
        if (Site::where('subdomain', $subdomain)->exists()) {
            return true;
        }

        // Check reserved/blocked subdomains
        $reserved = [
            'www', 'mail', 'ftp', 'localhost', 'admin', 'root', 'test',
            'api', 'blog', 'forum', 'shop', 'store', 'support', 'help',
            'docs', 'wiki', 'news', 'cdn', 'media', 'static', 'assets'
        ];

        return in_array(strtolower($subdomain), $reserved);
    }

    private function generateSubdomainSuggestions(string $subdomain): array
    {
        $suggestions = [];
        
        // Add numbers
        for ($i = 1; $i <= 5; $i++) {
            $suggestion = $subdomain . $i;
            if (!$this->isSubdomainTaken($suggestion)) {
                $suggestions[] = $suggestion;
            }
        }

        // Add common suffixes
        $suffixes = ['site', 'web', 'online', 'pro', 'hub'];
        foreach ($suffixes as $suffix) {
            $suggestion = $subdomain . '-' . $suffix;
            if (!$this->isSubdomainTaken($suggestion) && count($suggestions) < 5) {
                $suggestions[] = $suggestion;
            }
        }

        return array_slice($suggestions, 0, 3);
    }
}