<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\FrameCodec;
use App\Services\Migration\Package\PackageLimits;
use App\Services\Migration\Package\PackageWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * The layout is exact: no unused bytes anywhere, every entry starts where the previous one ended,
 * and the file ends right after the trailer. The package is walked by hand in the test helper, not
 * with the reader, so a layout bug cannot be hidden by a reader that shares it.
 */
class NoSlackLayoutTest extends TestCase
{
    use BuildsPackages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    #[Test]
    public function a_package_with_several_entries_has_no_slack_and_the_right_order(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('db/products/0001.ndjson.gz', 'db.rows', str_repeat('row,', 5000), ['group' => 'catalog', 'rows' => 5000, 'codec' => 'gzip', 'raw_len' => 90000]);
        $writer->addEntryFromString('report/export-report.json', 'report', '{"ok":true}');
        $result = $writer->seal();

        $walk = $this->walk($result['path']);

        $this->assertSame([0, 1, 2, PackageLimits::ENTRY_ID_TOC], array_keys($walk['entries']), 'meta first, data in order, table of contents last');
        $this->assertSame($result['size'], $walk['size']);
        $this->assertSame($result['sha256'], $walk['fileSha256']);
        $this->assertFileDoesNotExist($this->packagePath().'.part', 'the .part is gone once sealed');
        $this->assertDirectoryDoesNotExist($this->packagePath().'.part.ledger', 'and so is the ledger');

        // Every entry starts where the previous one ended.
        $ids = array_keys($walk['entries']);
        for ($i = 1; $i < count($ids); $i++) {
            $previous = $walk['entries'][$ids[$i - 1]];
            $this->assertSame($previous['offset'] + $previous['enc_len'], $walk['entries'][$ids[$i]]['offset'], "entry {$ids[$i]} follows entry {$ids[$i - 1]} with no gap");
        }
    }

    #[Test]
    public function the_table_of_contents_lists_every_entry_with_the_values_found_in_the_file(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('files/vol-0000.oevol', 'files.volume', 'volume bytes', ['group' => 'catalog_files', 'critical' => true]);
        $result = $writer->seal();

        $walk = $this->walk($result['path']);
        $tocEntries = array_column($walk['toc']['entries'], null, 'id');

        $this->assertSame([0, 1], array_keys($tocEntries), 'the toc lists meta and the data entry, not itself');
        foreach ($tocEntries as $id => $record) {
            $found = $walk['entries'][$id];
            $this->assertSame($found['offset'], $record['offset'], "offset of {$id}");
            $this->assertSame($found['enc_len'], $record['enc_len'], "enc_len of {$id}");
            $this->assertSame($found['enc_sha256'], $record['enc_sha256'], "enc_sha256 of {$id}");
            $this->assertSame(hash('sha256', $found['plain']), $record['plain_sha256'], "plain_sha256 of {$id}");
            $this->assertSame(strlen($found['plain']), $record['plain_len']);
        }

        $this->assertSame('catalog_files', $tocEntries[1]['group']);
        $this->assertTrue($tocEntries[1]['critical']);
        $this->assertSame(hash('sha256', $walk['entries'][0]['plain']), $walk['toc']['meta_sha256'], 'the toc is chained to the meta entry');
        $this->assertSame(bin2hex($walk['header']->packageId), $walk['toc']['package_id']);
        $this->assertFalse($walk['toc']['contains_secrets']);
    }

    #[Test]
    public function an_entry_of_more_than_one_block_has_a_constant_stride_and_a_short_last_frame(): void
    {
        $plain = random_bytes((1 << 20) * 2 + 4321);
        $source = $this->workDir.'/source.bin';
        file_put_contents($source, $plain);

        $writer = $this->newWriter();
        $record = $writer->addEntryFromFile('files/vol-0000.oevol', 'files.volume', $source);
        $result = $writer->seal();

        $this->assertSame(3, $record['frames']);

        $walk = $this->walk($result['path']);
        $this->assertSame($plain, $walk['entries'][1]['plain'], 'three frames decrypt back to the original bytes');
        $this->assertSame(FrameCodec::ENTRY_HEADER_LEN + 3 * 32 + strlen($plain), $walk['entries'][1]['enc_len']);
    }

    #[Test]
    public function an_empty_entry_is_valid(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/empty.txt', 'report', '');
        $result = $writer->seal();

        $walk = $this->walk($result['path']);

        $this->assertSame('', $walk['entries'][1]['plain']);
        $this->assertSame(1, $walk['entries'][1]['head']['frameCount'], 'one empty frame so the tag authenticates the entry');
    }

    #[Test]
    public function the_secrets_entry_has_its_own_key_class_and_the_toc_says_so_in_the_clear(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', '{}');
        $writer->addSecrets("{\"key\":\"payment.airwallex_api_key\",\"value\":\"x\"}\n");
        $result = $writer->seal();

        $walk = $this->walk($result['path']);

        $this->assertTrue($walk['toc']['contains_secrets']);
        $secrets = array_values(array_filter($walk['toc']['entries'], fn ($e) => $e['name'] === 'secrets'))[0];
        $this->assertSame(FrameCodec::KEY_CLASS_SECRETS, $secrets['key_class']);
        $this->assertSame(FrameCodec::KEY_CLASS_SECRETS, $walk['entries'][$secrets['id']]['head']['keyClass']);
    }

    #[Test]
    public function the_meta_entry_records_the_package_id_and_format_and_what_the_caller_said(): void
    {
        $writer = $this->newWriter('site.oeparts', ['source' => ['domain' => 'old.example'], 'format' => 'caller lies', 'package_id' => 'caller lies']);
        $result = $writer->seal();

        $walk = $this->walk($result['path']);
        $meta = json_decode($walk['entries'][0]['plain'], true);

        $this->assertSame('old.example', $meta['source']['domain']);
        $this->assertSame(bin2hex($walk['header']->packageId), $meta['package_id'], 'the writer, not the caller, decides the package id');
        $this->assertSame(['major' => 1, 'minor' => 0], $meta['format']);
    }

    #[Test]
    public function nothing_exists_at_the_final_path_until_the_package_is_sealed(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', '{}');

        $this->assertFileDoesNotExist($this->packagePath(), 'a half-written package must never look finished');
        $this->assertFileExists($this->packagePath().'.part');

        $writer->seal();

        $this->assertFileExists($this->packagePath());
        $this->assertFileDoesNotExist($this->packagePath().'.part');
    }

    #[Test]
    public function entry_names_must_be_valid_unique_and_not_reserved(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', '{}');

        foreach (['', '../x', 'UPPER', '/abs', 'has space', 'toc', 'meta', 'secrets', 'report/a.json', str_repeat('a', 193)] as $bad) {
            try {
                $writer->addEntryFromString($bad, 'report', '{}');
                $this->fail("'{$bad}' must be refused as an entry name");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $writer->abort();
    }

    #[Test]
    public function the_passphrase_rule_is_enforced_by_the_writer_so_every_door_gets_it(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('too_short');

        PackageWriter::create($this->packagePath('weak.oeparts'), 'short', [], $this->weakKdf(), kdfId: 2, kdfParams: ['p1' => 2000, 'p2' => 0]);
    }

    #[Test]
    public function creating_over_an_existing_package_or_an_unfinished_one_is_refused(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', '{}');

        try {
            $this->newWriter();
            $this->fail('A second writer over an unfinished package must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unfinished', $e->getMessage());
        }

        $writer->seal();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $this->newWriter();
    }
}
