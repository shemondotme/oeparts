<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug'];

    protected $casts = [
        'name' => 'array',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tags', 'tag_id', 'post_id');
    }
}
