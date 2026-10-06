<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $admin_id
 * @property bool $push_enabled
 * @property bool $sound_enabled
 * @property bool|null $hide_details
 * @property bool $quiet_enabled
 * @property string|null $quiet_start
 * @property string|null $quiet_end
 * @property array<string, bool>|null $topic_overrides
 */
class AdminPushPreference extends Model
{
    protected $fillable = [
        'admin_id', 'push_enabled', 'sound_enabled', 'hide_details',
        'quiet_enabled', 'quiet_start', 'quiet_end', 'topic_overrides',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
            'hide_details' => 'boolean',
            'quiet_enabled' => 'boolean',
            'topic_overrides' => 'array',
        ];
    }

    public static function forAdmin(Admin $admin): self
    {
        return static::firstOrCreate(['admin_id' => $admin->id])->refresh();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** null = no personal override for this topic. */
    public function topicOverride(string $topic): ?bool
    {
        $overrides = $this->topic_overrides ?? [];

        return array_key_exists($topic, $overrides) ? (bool) $overrides[$topic] : null;
    }
}
