<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterCampaignRecipient extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'subscriber_id', 'email',
        'status', 'sent_at', 'opened_at', 'clicked_at',
    ];

    protected $casts = [
        'status'     => 'string',
        'sent_at'    => 'datetime',
        'opened_at'  => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class);
    }

    public function subscriber(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class);
    }
}
