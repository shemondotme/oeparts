<?php

/*
 * Regenerates the two golden .oeparts files in this directory. Run ONLY on purpose:
 *
 *     docker compose exec laravel.test php tests/Fixtures/oeparts/generate.php
 *
 * The files are committed because their whole point is that they never change: every future
 * version of the reader must open them. Frame IVs are random, so a regeneration produces
 * different bytes and requires re-running independent_reader.py to refresh expected.json
 * (see README.md in this directory). The salt and package id are fixed to make the headers readable.
 *
 * Both files use PRODUCTION key-derivation settings (Argon2id 2 passes / 32 MiB, PBKDF2 300 000
 * iterations: the lowest values a reader accepts), so they open with the default Kdf.
 */

require __DIR__.'/../../../vendor/autoload.php';

use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackageWriter;

$passphrase = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

$pattern = '';
for ($i = 0; $i < 150000; $i++) {
    $pattern .= chr(($i * 7 + 3) % 251);
}

$rows = str_repeat("INSERT INTO t (id) VALUES (1);\n", 3000)."-- end\n";
$gzip = gzencode(substr($rows, 0, 40000), 6).gzencode(substr($rows, 40000), 6);

$variants = [
    'v1-argon2id.oeparts' => [PackageFormat::KDF_ARGON2ID, ['p1' => 2, 'p2' => 32768], hex2bin('000102030405060708090a0b0c0d0e0f'), hex2bin('a1a2a3a4a5a6a7a8a9aaabacadaeafb0')],
    'v1-pbkdf2.oeparts' => [PackageFormat::KDF_PBKDF2_SHA512, ['p1' => 300000, 'p2' => 0], hex2bin('101112131415161718191a1b1c1d1e1f'), hex2bin('c1c2c3c4c5c6c7c8c9cacbcccdcecfd0')],
];

foreach ($variants as $file => [$kdfId, $params, $salt, $packageId]) {
    $path = __DIR__.'/'.$file;
    @unlink($path);

    $writer = PackageWriter::create(
        $path, $passphrase,
        ['source' => ['domain' => 'fixture.example'], 'created_at' => '2026-01-01T00:00:00Z', 'app_version' => '2.1.0', 'groups' => ['settings', 'files', 'catalog']],
        kdfId: $kdfId, kdfParams: $params, salt: $salt, packageId: $packageId, blockLog2: PackageFormat::MIN_BLOCK_LOG2,
    );

    $writer->addEntryFromString('report/hello.json', 'report', '{"hello":"oeparts","n":1}', ['group' => 'settings']);
    $writer->addEntryFromString('files/pattern.bin', 'files', $pattern, ['group' => 'files']);
    $writer->addEntryFromString('db/rows.sql', 'db', $gzip, ['group' => 'catalog', 'codec' => 'gzip', 'raw_len' => strlen($rows)]);
    $writer->addSecrets("{\"key\":\"mail.password\",\"value\":\"hunter2\"}\n");

    $result = $writer->seal();
    echo $file.': '.$result['size']." bytes, fingerprint {$result['fingerprint']}\n";
}
