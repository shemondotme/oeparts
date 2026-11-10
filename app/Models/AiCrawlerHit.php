<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One day of visits by one AI crawler — see the create_ai_crawler_hits_table migration. */
class AiCrawlerHit extends Model
{
    public $timestamps = false;

    protected $fillable = ['hit_date', 'bot', 'hits', 'last_seen_at'];

    protected $casts = [
        'hit_date' => 'date',
        'hits' => 'integer',
        'last_seen_at' => 'datetime',
    ];
}
