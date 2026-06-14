<?php

namespace App\Enums;

enum AdminNotificationCategory: string
{
    case System    = 'system';
    case Orders    = 'orders';
    case Inventory = 'inventory';
    case Admin     = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::System    => 'System',
            self::Orders    => 'Orders',
            self::Inventory => 'Inventory',
            self::Admin     => 'Admin',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::System    => '⚠️',
            self::Orders    => '📦',
            self::Inventory => '🔍',
            self::Admin     => '👤',
        };
    }

    public function cssAccent(): string
    {
        return match ($this) {
            self::System    => 'var(--accent-warning)',
            self::Orders    => 'var(--accent-brand)',
            self::Inventory => 'var(--accent-success)',
            self::Admin     => 'var(--color-text-muted)',
        };
    }
}
