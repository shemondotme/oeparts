<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\Exceptions\LimitExceededException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\PackageLimits;
use App\Services\Migration\Package\PackageReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;
use Tests\Unit\Migration\Package\Concerns\ForgesPackages;

/**
 * The table of contents is authenticated, but that only proves who wrote it, not that it is true
 * (a buggy or hostile writer holding the passphrase signs whatever it likes). The reader therefore
 * validates every record against the layout rules before trusting any entry. Each case here is a
 * table of contents encrypted with the package's real keys that breaks exactly one rule.
 */
class ForgedTocTest extends TestCase
{
    use BuildsPackages;
    use ForgesPackages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();

        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', str_repeat('A', 200), ['group' => 'settings']);
        $writer->addEntryFromString('report/b.json', 'report', str_repeat('B', 300));
        $writer->addSecrets("{\"key\":\"k\",\"value\":\"v\"}\n");
        $writer->seal();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    private function open(): PackageReader
    {
        return PackageReader::open($this->packagePath(), $this->passphrase, $this->weakKdf());
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
     * @param  class-string<PackageException>  $expected
     */
    private function assertForgeryRejected(string $why, callable $mutate, string $expected = CorruptException::class): void
    {
        $this->removeWorkDir();
        $this->setUp();

        $this->forgeToc($this->packagePath(), $mutate);

        try {
            $this->open();
            $this->fail("{$why}: the package was accepted");
        } catch (PackageException $e) {
            $this->assertInstanceOf($expected, $e, $why);
        }
    }

    #[Test]
    public function a_toc_rewritten_without_changes_is_still_accepted(): void
    {
        $this->forgeToc($this->packagePath(), fn (array $toc): array => $toc);

        $this->assertSame(4, count($this->open()->toc()['entries']));
    }

    #[Test]
    public function each_broken_layout_rule_is_refused(): void
    {
        $cases = [
            'a gap before an entry' => function (array $toc): array {
                $toc['entries'][1]['offset'] += 1;

                return $toc;
            },
            'overlapping entries' => function (array $toc): array {
                $toc['entries'][2]['offset'] -= 5;

                return $toc;
            },
            'ids out of order' => function (array $toc): array {
                $toc['entries'][1]['id'] = 7;

                return $toc;
            },
            'a wrong frame count' => function (array $toc): array {
                $toc['entries'][1]['frames'] = 9;

                return $toc;
            },
            'a wrong stored length' => function (array $toc): array {
                $toc['entries'][1]['enc_len'] += 1;

                return $toc;
            },
            'a wrong plain length' => function (array $toc): array {
                $toc['entries'][1]['plain_len'] += 1;

                return $toc;
            },
            'a path-like name' => function (array $toc): array {
                $toc['entries'][1]['name'] = '../../etc/passwd';

                return $toc;
            },
            'an upper-case name' => function (array $toc): array {
                $toc['entries'][1]['name'] = 'Report/A.json';

                return $toc;
            },
            'a repeated name' => function (array $toc): array {
                $toc['entries'][2]['name'] = $toc['entries'][1]['name'];

                return $toc;
            },
            'the reserved name toc' => function (array $toc): array {
                $toc['entries'][1]['name'] = 'toc';

                return $toc;
            },
            'a second meta' => function (array $toc): array {
                $toc['entries'][1]['name'] = 'meta';

                return $toc;
            },
            'the first entry not being the meta' => function (array $toc): array {
                $toc['entries'][0]['name'] = 'report/zero.json';

                return $toc;
            },
            'the secrets entry under the data key class' => function (array $toc): array {
                $toc['entries'][3]['key_class'] = 0;

                return $toc;
            },
            'a data entry under the secrets key class' => function (array $toc): array {
                $toc['entries'][1]['key_class'] = 1;

                return $toc;
            },
            'an unknown key class' => function (array $toc): array {
                $toc['entries'][1]['key_class'] = 9;

                return $toc;
            },
            'an unknown codec' => function (array $toc): array {
                $toc['entries'][1]['codec'] = 'zstd';

                return $toc;
            },
            'a stored entry announcing a different raw size' => function (array $toc): array {
                $toc['entries'][1]['raw_len'] = 5;

                return $toc;
            },
            'a malformed checksum' => function (array $toc): array {
                $toc['entries'][1]['enc_sha256'] = 'xyz';

                return $toc;
            },
            'a string where an integer belongs' => function (array $toc): array {
                $toc['entries'][1]['offset'] = (string) $toc['entries'][1]['offset'];

                return $toc;
            },
            'a negative length' => function (array $toc): array {
                $toc['entries'][1]['plain_len'] = -1;

                return $toc;
            },
            'a missing field' => function (array $toc): array {
                unset($toc['entries'][1]['frames']);

                return $toc;
            },
            'an entry list that is not a list' => function (array $toc): array {
                $toc['entries'] = ['a' => $toc['entries'][0]];

                return $toc;
            },
            'an empty entry list' => function (array $toc): array {
                $toc['entries'] = [];

                return $toc;
            },
            'a missing entry list' => function (array $toc): array {
                unset($toc['entries']);

                return $toc;
            },
            'the last entry stopping short of the table of contents' => function (array $toc): array {
                array_pop($toc['entries']);

                return $toc;
            },
            'a different package id' => function (array $toc): array {
                $toc['package_id'] = str_repeat('0', 32);

                return $toc;
            },
            'a different format major' => function (array $toc): array {
                $toc['format']['major'] = 2;

                return $toc;
            },
            'a meta checksum that is not the meta entry' => function (array $toc): array {
                $toc['meta_sha256'] = str_repeat('a', 64);

                return $toc;
            },
            'totals that do not add up' => function (array $toc): array {
                $toc['totals']['plain_bytes'] += 1;

                return $toc;
            },
            'an entry count that is not the entry list' => function (array $toc): array {
                $toc['totals']['entries'] = 99;

                return $toc;
            },
        ];

        foreach ($cases as $why => $mutate) {
            $this->assertForgeryRejected($why, $mutate);
        }
    }

    #[Test]
    public function limits_that_are_exceeded_are_reported_as_limits(): void
    {
        $this->assertForgeryRejected('an entry larger than the limit', function (array $toc): array {
            $toc['entries'][1]['plain_len'] = PackageLimits::MAX_ENTRY_BYTES + 1;
            $toc['entries'][1]['raw_len'] = PackageLimits::MAX_ENTRY_BYTES + 1;

            return $toc;
        }, CorruptException::class);

        $this->assertForgeryRejected('a raw size beyond the decompression limit', function (array $toc): array {
            $toc['entries'][1]['codec'] = 'gzip';
            $toc['entries'][1]['raw_len'] = PackageLimits::MAX_ENTRY_RAW_BYTES + 1;

            return $toc;
        }, CorruptException::class);

        $this->assertForgeryRejected('a package that would unpack beyond the total limit', function (array $toc): array {
            // 130 empty-looking entries that each announce the largest allowed decompressed size.
            $meta = $toc['entries'][0];
            $offset = $meta['offset'] + $meta['enc_len'];
            $entries = [$meta];

            for ($i = 1; $i <= 130; $i++) {
                $entries[] = [
                    'id' => $i, 'name' => "bulk/e{$i}", 'kind' => 'db', 'group' => null, 'critical' => false, 'key_class' => 0,
                    'offset' => $offset, 'frames' => 1, 'plain_len' => 0, 'enc_len' => 56, 'enc_sha256' => str_repeat('a', 64),
                    'plain_sha256' => str_repeat('b', 64), 'codec' => 'gzip', 'raw_len' => PackageLimits::MAX_ENTRY_RAW_BYTES,
                    'rows' => null, 'attrs' => [],
                ];
                $offset += 56;
            }

            $toc['entries'] = $entries;

            return $toc;
        }, LimitExceededException::class);
    }

    #[Test]
    public function a_toc_that_is_not_json_or_not_an_object_is_corruption(): void
    {
        foreach (['not json at all', '[1,2,3]', '"a string"', 'null', '{"entries":'] as $plain) {
            $this->removeWorkDir();
            $this->setUp();
            $this->forgeRawToc($this->packagePath(), $plain);

            try {
                $this->open();
                $this->fail("'{$plain}' was accepted");
            } catch (CorruptException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_toc_nested_deeper_than_the_limit_is_corruption_not_a_crash(): void
    {
        $this->forgeRawToc($this->packagePath(), str_repeat('[', 200).str_repeat(']', 200));

        $this->expectException(CorruptException::class);
        $this->open();
    }
}
