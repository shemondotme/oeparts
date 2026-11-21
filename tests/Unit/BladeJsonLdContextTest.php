<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * JSON-LD needs the key "@context". In Blade markup (and inside a {!! !!} echo) a literal @ before a word has to be
 * written "@@" because "@context" would be read as a directive. Inside a raw @php ... @endphp block it is the other
 * way round: Blade copies that block verbatim, so "@@context" is emitted as "@@context" and the structured data is
 * invalid. That is exactly what the OEM part pages and the zero-result page did (about 1.7 million pages) until it
 * was found in the 2026-11-21 SEO audit; no unit test looks at the rendered key, so this one reads the templates.
 */
class BladeJsonLdContextTest extends TestCase
{
    #[Test]
    public function no_php_block_writes_a_doubled_at_context(): void
    {
        $offenders = [];
        $root = dirname(__DIR__, 2).'/resources/views';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            if (! str_contains($source, '@@context')) {
                continue;
            }

            if (preg_match_all('/@php\b(?!\s*\()(.*?)@endphp/s', $source, $blocks)) {
                foreach ($blocks[1] as $block) {
                    // Comments may talk about it; only a real array key or JSON key is a defect.
                    if (preg_match('/[\'"]@@context[\'"]/', $block)) {
                        $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), 'Use \'@context\' inside @php blocks (Blade only turns @@ into @ in markup and echoes).');
    }
}
