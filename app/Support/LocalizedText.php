<?php

namespace App\Support;

/**
 * Locale-STRICT reads of a translatable (per-locale JSON) field.
 *
 * trans_field() deliberately falls back to English (then to anything) when
 * the requested locale is empty — right for names and UI text. For SEO text
 * that is wrong: serving an English title or intro paragraph on /de/… makes
 * the page look partly untranslated and mixes languages on one URL. Here an
 * empty locale means "not written yet", and the caller falls back to the
 * localized template instead.
 */
final class LocalizedText
{
    public static function exact(array|string|null $field, ?string $locale = null): string
    {
        if (is_string($field)) {
            return trim($field);
        }

        if (empty($field)) {
            return '';
        }

        return trim((string) ($field[$locale ?? app()->getLocale()] ?? ''));
    }

    /**
     * Plain text → safe HTML paragraphs (blank-line separated), so an editor
     * can write a few paragraphs in a textarea without being able to inject
     * markup.
     *
     * @return list<string> escaped paragraph strings
     */
    public static function paragraphs(string $text): array
    {
        $parts = preg_split('/\R{2,}/', trim($text)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $p) => nl2br(e(trim($p)), false),
            $parts
        ), fn (string $p) => $p !== ''));
    }
}
