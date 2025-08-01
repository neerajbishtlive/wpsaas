<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    // Only include columns that actually exist in your database
    // Run 'php artisan diagnose:sites' to see what columns you have
    protected $fillable = [
        'subdomain',
        'user_id',
        'plan_id',
        'status',
        'expires_at',
        'suspended_at',
        'db_name',
        'db_user',
        'db_password',
        'db_prefix',
        'admin_email',
        'admin_username',
        'site_title',
        'settings',
        // Only uncomment if these columns exist:
        // 'usage_violations',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'suspended_at' => 'datetime',
        'settings' => 'array',
    ];

    // Safe attributes - only use columns that exist
    protected $attributes = [
        'settings' => '[]',
    ];

    // Add this method to help debug
    public function getSafeAttributes($data)
    {
        // Only return attributes that are fillable
        return array_intersect_key($data, array_flip($this->fillable));
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function statistics(): HasMany
    {
        return $this->hasMany(SiteStatistic::class);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the full URL for the site using subdomain
     */
    public function getSiteUrl(): string
    {
        $domain = config('saas.current_domain');
        $protocol = config('saas.current_protocol');
        
        // Call the closure to get the actual value
        if (is_callable($domain)) {
            $domain = $domain();
        }
        if (is_callable($protocol)) {
            $protocol = $protocol();
        }
        
        // For local development with port
        if (app()->environment('local')) {
            return "{$protocol}://{$this->subdomain}.{$domain}:8000";
        }
        
        return "{$protocol}://{$this->subdomain}.{$domain}";
    }

    /**
     * Get the full URL for the site (alias for getSiteUrl)
     */
    public function getUrlAttribute(): string
    {
        return $this->getSiteUrl();
    }

    /**
     * Get the WordPress admin URL
     */
    public function getAdminUrl(): string
    {
        return $this->getSiteUrl() . '/wp-admin';
    }

    /**
     * Get the WordPress admin URL attribute
     */
    public function getAdminUrlAttribute(): string
    {
        return $this->getAdminUrl();
    }

    /**
     * Get the WordPress login URL
     */
    public function getLoginUrlAttribute(): string
    {
        return $this->getSiteUrl() . '/wp-login.php';
    }

    /**
     * Get the site path on disk
     */
    public function getSitePath(): string
    {
        return config('wordpress.sites_path', base_path('../sites')) . '/' . $this->subdomain;
    }

    /**
     * Get domain name for display
     */
    public function getDomainAttribute(): string
    {
        $domain = config('saas.current_domain');
        if (is_callable($domain)) {
            $domain = $domain();
        }
        return $this->subdomain . '.' . $domain;
    }
}