<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RefundStatus: string implements HasLabel
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Processed = 'processed';
    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Processed => 'Processed',
        };
    }
}
