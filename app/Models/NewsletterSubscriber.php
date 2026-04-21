<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email', 'lang', 'is_active',
        'subscribed_at', 'unsubscribed_at', 'ip_address',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'subscribed_at'   => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];
}
