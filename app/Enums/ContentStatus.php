<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
}
