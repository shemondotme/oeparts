<?php

namespace App\Services\Cleanup\Exceptions;

use RuntimeException;

/** A cleanup request that is not allowed or cannot be carried out (report-only target, unknown mode, ...). */
class CleanupException extends RuntimeException {}
