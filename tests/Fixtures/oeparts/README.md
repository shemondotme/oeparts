# Golden `.oeparts` fixtures

`v1-argon2id.oeparts` and `v1-pbkdf2.oeparts` are format-version-1.0 packages that are **committed
and never regenerated casually**: every future version of the reader has to open them
(`CompatibilityPolicyTest`). Both use production key-derivation settings (the lowest a reader
accepts), a 64 KiB frame size so a 150 KB entry spans three frames, a two-member gzip entry and a
secrets entry. The passphrase is in `expected.json`.

`expected.json` was produced by `independent_reader.py`, a reader written from the format
description alone (Python `cryptography` and `argon2-cffi`, no PHP code), so PHP and Python agree on
the format rather than on each other's bugs. `generate.php` made the files.

## Re-checking with the independent reader (Gate F, from the host)

    pip install cryptography argon2-cffi
    python tests/Fixtures/oeparts/independent_reader.py tests/Fixtures/oeparts/v1-argon2id.oeparts "MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA" --secrets

The output must equal the matching entry in `expected.json`.

## Regenerating (only for a deliberate format change)

    docker compose exec laravel.test php tests/Fixtures/oeparts/generate.php

IVs are random, so the bytes change; re-run the independent reader and refresh `expected.json`.
A format change that makes the OLD fixtures unreadable is a major-version change, not a regeneration.
