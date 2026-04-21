<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageString extends Model
{
    protected $fillable = ['lang_code', 'group', 'key', 'value'];
}
