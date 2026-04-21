<?php

namespace App\Enums;

enum SettingType: string
{
    case String    = 'string';
    case Boolean   = 'boolean';
    case Integer   = 'integer';
    case Decimal   = 'decimal';
    case Json      = 'json';
    case Encrypted = 'encrypted';
}
