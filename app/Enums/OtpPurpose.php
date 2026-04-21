<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case GuestCheckout = 'guest_checkout';
    case ContactForm   = 'contact_form';
    case EmailVerify   = 'email_verify';
}
