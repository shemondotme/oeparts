<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $topic
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 */
class AdminPushDeferred extends Model
{
    protected $table = 'admin_push_deferred';

    public const UPDATED_AT = null;

    protected $fillable = ['admin_id', 'topic', 'title', 'body', 'url'];
}
