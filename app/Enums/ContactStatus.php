<?php

namespace App\Enums;

enum ContactStatus: string
{
    case Unread   = 'unread';
    case Read     = 'read';
    case Resolved = 'resolved';
}
