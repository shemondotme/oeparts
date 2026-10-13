<?php

namespace App\Console\Concerns;

/** The command line gave no usable passphrase source, or gave one that is not allowed. Maps to exit code 2. */
class PassphraseSourceException extends \RuntimeException {}
