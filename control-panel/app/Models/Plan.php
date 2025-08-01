<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'stripe_price_id',
        'trial_days',
        'features',
        'limits',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'limits' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    /**
     * Scope a query to only include active plans.
     */
    public function scopeActive($query)
    {
        // Check if column exists before using it
        if (Schema::hasColumn('plans', 'is_active')) {
            return $query->where('is_active', true);
        }
        return $query;
    }

    /**
     * Scope a query to only include public plans.
     */
    public function scopePublic($query)
    {
        // Check if column exists before using it
        if (Schema::hasColumn('plans', 'is_public')) {
            return $query->where('is_public', true);
        }
        return $query;
    }

    /**
     * Get all sites for this plan.
     */
    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Get all users subscribed to this plan.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'subscriptions')
                    ->withPivot('status', 'trial_ends_at', 'ends_at')
                    ->withTimestamps();
    }

    /**
     * Check if plan has trial.
     */
    public function hasTrial()
    {
        return $this->trial_days > 0;
    }

    /**
     * Check if plan is free.
     */
    public function isFree()
    {
        return $this->price == 0;
    }
}