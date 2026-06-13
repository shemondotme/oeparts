<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending          = 'pending';
    case Paid             = 'paid';
    case Processing       = 'processing';
    case Shipped          = 'shipped';
    case Delivered        = 'delivered';
    case Cancelled        = 'cancelled';
    case RefundRequested  = 'refund_requested';
    case Refunded         = 'refunded';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match($this) {
            self::Pending         => 'Pending',
            self::Paid            => 'Paid',
            self::Processing      => 'Processing',
            self::Shipped         => 'Shipped',
            self::Delivered       => 'Delivered',
            self::Cancelled       => 'Cancelled',
            self::RefundRequested => 'Refund Requested',
            self::Refunded        => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'info',
            self::Processing => 'primary',
            self::Shipped, self::Delivered => 'success',
            self::Cancelled => 'danger',
            self::RefundRequested => 'warning',
            self::Refunded => 'gray',
        };
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Pending, self::Paid, self::Processing]);
    }
}
