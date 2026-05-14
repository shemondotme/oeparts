<?php

namespace App\Enums;

enum ProductCondition: string
{
    case New  = 'new';
    case Used = 'used';

    public function label(): string
    {
        return match($this) {
            self::New  => 'New',
            self::Used => 'Used',
        };
    }

    public function badgeBg(): string
    {
        return match($this) {
            self::New  => '#DCFCE7',
            self::Used => '#DBEAFE',
        };
    }

    public function badgeText(): string
    {
        return match($this) {
            self::New  => '#16A34A',
            self::Used => '#1D4ED8',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::New  => 'bg-emerald-100 text-emerald-800',
            self::Used => 'bg-blue-100 text-blue-800',
        };
    }
}
