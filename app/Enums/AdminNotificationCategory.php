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

    /**
     * Heroicon identifier for rendering in Filament's own notification
     * bell (icon()/cssAccent() above use emoji/CSS-var values meant for a
     * bespoke UI that was never built — see AdminDashboardNotification).
     */
    public function filamentIcon(): string
    {
        return match ($this) {
            self::System    => 'heroicon-o-exclamation-triangle',
            self::Orders    => 'heroicon-o-shopping-bag',
            self::Inventory => 'heroicon-o-archive-box',
            self::Admin     => 'heroicon-o-user',
        };
    }

    /**
     * Filament color name (warning/primary/success/gray), not a raw CSS
     * variable — see filamentIcon()'s docblock.
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::System    => 'warning',
            self::Orders    => 'primary',
            self::Inventory => 'success',
            self::Admin     => 'gray',
        };
    }
}
