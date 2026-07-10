<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmailTemplate: string implements HasLabel
{
    case OrderConfirmation  = 'order_confirmation';
    case OrderStatus        = 'order_status';
    case OrderShipped       = 'order_shipped';
    case Welcome            = 'welcome';
    case Otp                = 'otp';
    case RefundProcessed    = 'refund_processed';
    case AbandonedCart      = 'abandoned_cart';
    case NewsletterConfirm  = 'newsletter_confirm';
    case PasswordReset      = 'password_reset';
    case ContactReply       = 'contact_reply';
    case PartInquiryStatus  = 'part_inquiry_status';
    // Catch-all so unrecognized mailables are logged honestly instead of
    // being misfiled as order confirmations.
    case Other              = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::OrderConfirmation => 'Order Confirmation',
            self::OrderStatus => 'Order Status',
            self::OrderShipped => 'Order Shipped',
            self::Welcome => 'Welcome',
            self::Otp => 'OTP',
            self::RefundProcessed => 'Refund Processed',
            self::AbandonedCart => 'Abandoned Cart',
            self::NewsletterConfirm => 'Newsletter Confirmation',
            self::PasswordReset => 'Password Reset',
            self::ContactReply => 'Contact Reply',
            self::PartInquiryStatus => 'Part Inquiry Status',
            self::Other => 'Other',
        };
    }
}
