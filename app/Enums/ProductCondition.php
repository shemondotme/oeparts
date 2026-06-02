<?php

namespace App\Enums;

enum ProductCondition: string
{
    case New             = 'new';
    case Used            = 'used';
    case UsedGradeA      = 'used_grade_a';
    case UsedGradeB      = 'used_grade_b';
    case UsedGradeC      = 'used_grade_c';
    case Remanufactured  = 'remanufactured';
    case Aftermarket     = 'aftermarket';
    case NewOldStock     = 'new_old_stock';

    public function label(): string
    {
        return match($this) {
            self::New             => 'New',
            self::Used            => 'Used',
            self::UsedGradeA      => 'Used (Grade A)',
            self::UsedGradeB      => 'Used (Grade B)',
            self::UsedGradeC      => 'Used (Grade C)',
            self::Remanufactured  => 'Remanufactured',
            self::Aftermarket     => 'Aftermarket',
            self::NewOldStock     => 'New Old Stock',
        };
    }

    public function badgeBg(): string
    {
        return match($this) {
            self::New             => '#DCFCE7',
            self::Used            => '#DBEAFE',
            self::UsedGradeA      => '#DBEAFE',
            self::UsedGradeB      => '#FEF3C7',
            self::UsedGradeC      => '#F1F5F9',
            self::Remanufactured  => '#F3E8FF',
            self::Aftermarket     => '#FEE2E2',
            self::NewOldStock     => '#ECFDF5',
        };
    }

    public function badgeText(): string
    {
        return match($this) {
            self::New             => '#16A34A',
            self::Used            => '#1D4ED8',
            self::UsedGradeA      => '#1D4ED8',
            self::UsedGradeB      => '#D97706',
            self::UsedGradeC      => '#64748B',
            self::Remanufactured  => '#7C3AED',
            self::Aftermarket     => '#DC2626',
            self::NewOldStock     => '#059669',
        };
    }
}
