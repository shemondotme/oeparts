<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $label
 * @property string $urgency
 * @property bool $push_enabled
 * @property bool $sound_enabled
 * @property array<int, string>|null $allowed_roles
 * @property bool $is_auto
 */
class PushTopic extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'description', 'urgency',
        'push_enabled', 'sound_enabled', 'allowed_roles', 'is_auto', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
            'is_auto' => 'boolean',
            'allowed_roles' => 'array',
        ];
    }

    public function isUrgent(): bool
    {
        return $this->urgency === 'urgent';
    }

    /** Whether an admin holding $roles may receive this topic at all. */
    public function allowsRoles(array $roles): bool
    {
        $allowed = $this->allowed_roles;

        if (empty($allowed)) {
            return true;
        }

        return count(array_intersect($allowed, $roles)) > 0;
    }
}
