<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Relay extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inbox_url',
        'actor_url',
        'is_active',
        'following',
        'metadata',
        'last_successful_delivery_at',
        'last_failed_delivery_at',
        'failed_delivery_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'following' => 'boolean',
        'metadata' => 'array',
        'last_successful_delivery_at' => 'datetime',
        'last_failed_delivery_at' => 'datetime',
    ];

    /**
     * Get active relays
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get following relays
     */
    public function scopeFollowing($query)
    {
        return $query->where('following', true);
    }

    /**
     * Get active and following relays
     */
    public function scopeActiveAndFollowing($query)
    {
        return $query->where('is_active', true)->where('following', true);
    }

    /**
     * Check if this relay is healthy
     */
    public function isHealthy()
    {
        // Consider a relay healthy if it hasn't failed more than 10 times in a row
        // or if it has had a successful delivery in the last 24 hours
        if ($this->failed_delivery_count >= 10) {
            return $this->last_successful_delivery_at &&
                   $this->last_successful_delivery_at->gt(now()->subDay());
        }

        return true;
    }

    /**
     * Mark a successful delivery
     */
    public function markSuccessfulDelivery()
    {
        $this->update([
            'last_successful_delivery_at' => now(),
            'failed_delivery_count' => 0,
        ]);
    }

    /**
     * Mark a failed delivery
     */
    public function markFailedDelivery()
    {
        $this->increment('failed_delivery_count');
        $this->update(['last_failed_delivery_at' => now()]);
    }

    /**
     * Get the relay's actor URL or derive it from inbox URL
     */
    public function getActorUrlAttribute($value)
    {
        if ($value) {
            return $value;
        }

        // Try to derive actor URL from inbox URL
        // For AodeRelay, actor URL is typically the same as inbox URL without /inbox
        if ($this->inbox_url) {
            return rtrim(str_replace('/inbox', '', $this->inbox_url), '/');
        }

        return null;
    }

    /**
     * Get display name for the relay
     */
    public function getDisplayNameAttribute()
    {
        if ($this->name) {
            return $this->name;
        }

        if ($this->actor_url) {
            return parse_url($this->actor_url, PHP_URL_HOST);
        }

        if ($this->inbox_url) {
            return parse_url($this->inbox_url, PHP_URL_HOST);
        }

        return 'Unknown Relay';
    }
}
