<?php

namespace App\Enums;

enum RedirectType: string
{
    case Permanent = '301';
    case Temporary = '302';
}
