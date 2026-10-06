<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $endpoint
 * @property string $public_key
 * @property string $auth_token
 * @property string|null $content_encoding
 * @property int $failure_count
 * @property Carbon|null $last_success_at
 */
class AdminPushSubscription extends Model
{
    protected $fillable = [
        'admin_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token',
        'content_encoding', 'user_agent', 'device_label',
        'last_success_at', 'last_failure_at', 'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
        ];
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
