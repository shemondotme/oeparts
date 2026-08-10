<?php

namespace App\Models;

use App\Support\DatabaseTranslationLoader;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LanguageString extends Model
{
    use HasFactory;

    protected $fillable = ['lang_code', 'group', 'key', 'value'];

    protected static function booted(): void
    {
        // DatabaseTranslationLoader caches each (locale, group) override set
        // forever — without this, an edited translation wouldn't actually
        // take effect until the cache expired some other way.
        static::saved(fn (LanguageString $s) => DatabaseTranslationLoader::forget($s->lang_code, $s->group));
        static::deleted(fn (LanguageString $s) => DatabaseTranslationLoader::forget($s->lang_code, $s->group));
    }
}
