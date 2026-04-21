<?php

namespace App\Enums;

enum PartInquiryStatus: string
{
    case New        = 'new';
    case Reviewing  = 'reviewing';
    case Sourced    = 'sourced';
    case Unavailable = 'unavailable';
}
