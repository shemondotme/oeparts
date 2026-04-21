<?php

namespace App\Enums;

enum LoginUserType: string
{
    case Admin    = 'admin';
    case Customer = 'customer';
}
