<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(NewsletterCampaignRecipient::class);
    }

    protected $fillable = [
        'email', 'lang', 'is_active',
        'subscribed_at', 'unsubscribed_at', 'ip_address',
        'unsubscribe_token',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'subscribed_at'   => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
