<?php

namespace App\Filament\Support;

use App\Models\FailedJob;
use Illuminate\Support\Str;

/**
 * Best-effort, safety-conscious decoder for a failed job's stored payload —
 * powers the "View exception" modal's payload/argument preview on the admin
 * Failed Jobs page (App\Filament\Pages\System\FailedJobsPage).
 *
 * Split into its own static-helper file (matching AdminUi.php's convention,
 * not a app/Services/ DI class — this is a pure string-in/array-out
 * transform) because the argument-decoding path is safety-sensitive and
 * benefits from being unit-tested in isolation with hand-crafted payload
 * strings, independent of any Livewire/Filament scaffolding.
 */
class FailedJobPayloadDecoder
{
    private const MAX_ARGUMENTS = 20;

    private const MAX_VALUE_LENGTH = 200;

    /**
     * @return array{meta: array<string, mixed>, arguments: ?array<string, string>, argumentsError: ?string}
     */
    public static function decode(FailedJob $record): array
    {
        $payload = json_decode($record->payload ?? '{}', true) ?? [];

        return [
            'meta' => self::extractMeta($payload),
            ...self::extractArguments($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function extractMeta(array $payload): array
    {
        return array_filter([
            'Job'             => $payload['displayName'] ?? $payload['job'] ?? null,
            'Max tries'       => $payload['maxTries'] ?? null,
            'Max exceptions'  => $payload['maxExceptions'] ?? null,
            'Timeout'         => isset($payload['timeout']) ? "{$payload['timeout']}s" : null,
            'Retry until'     => $payload['retryUntil'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{arguments: ?array<string, string>, argumentsError: ?string}
     */
    private static function extractArguments(array $payload): array
    {
        $command = $payload['data']['command'] ?? null;

        if (! is_string($command) || $command === '') {
            return ['arguments' => null, 'argumentsError' => 'No command payload found.'];
        }

        // Laravel's own queue:retry (RetryCommand::getInstanceFromPayload())
        // branches the same way: an `O:`-prefixed string is plain serialized
        // PHP; anything else is assumed to be an encrypted ShouldBeEncrypted
        // command. Core decrypts-then-unserializes those with no
        // allowed_classes restriction to actually execute the job — an
        // acceptable risk for its own retry-and-run flow, not one worth
        // replicating here for a passive preview. Encrypted commands are
        // simply not decoded.
        if (! str_starts_with($command, 'O:')) {
            return ['arguments' => null, 'argumentsError' => "Cannot preview — this job's payload is encrypted."];
        }

        try {
            // allowed_classes: false turns every serialized object into a
            // __PHP_Incomplete_Class — no constructor/__wakeup()/__destruct()
            // ever runs, eliminating PHP object-injection risk while still
            // preserving every property for inspection. Bonus: Eloquent
            // model arguments (via SerializesModels) surface as their raw
            // ModelIdentifier shape (class + id) without a DB hit, since
            // __wakeup() — which normally re-hydrates the real model — never
            // fires either.
            $decoded = @unserialize($command, ['allowed_classes' => false]);

            if ($decoded === false && $command !== 'b:0;') {
                return ['arguments' => null, 'argumentsError' => 'Could not decode: malformed serialized data.'];
            }

            return ['arguments' => self::propertiesOf($decoded), 'argumentsError' => null];
        } catch (\Throwable $e) {
            return ['arguments' => null, 'argumentsError' => 'Could not decode: ' . $e->getMessage()];
        }
    }

    /**
     * @return array<string, string>
     */
    private static function propertiesOf(mixed $decoded): array
    {
        if (! is_object($decoded)) {
            return ['value' => self::stringify($decoded)];
        }

        $properties = [];

        foreach ((array) $decoded as $key => $value) {
            // __PHP_Incomplete_Class_Name is PHP's own bookkeeping property
            // (holding the original class name) added to every object cast
            // this way — not a real job argument, so it's excluded rather
            // than shown as one.
            if ($key === '__PHP_Incomplete_Class_Name') {
                continue;
            }

            // __PHP_Incomplete_Class (and private/protected properties in
            // general) cast with PHP's mangled name prefix — "\0*\0name" for
            // protected, "\0ClassName\0name" for private. Strip it; this is
            // the standard idiom for this exact situation.
            $cleanKey = preg_replace('/^\0.*\0/', '', (string) $key);

            if (count($properties) >= self::MAX_ARGUMENTS) {
                $properties['…'] = '+ ' . (count((array) $decoded) - self::MAX_ARGUMENTS) . ' more';
                break;
            }

            $properties[$cleanKey] = self::stringify($value);
        }

        return $properties;
    }

    private static function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            $string = (string) $value;
        } else {
            // JSON_PARTIAL_OUTPUT_ON_ERROR degrades gracefully instead of
            // throwing on un-encodable things (a nested incomplete-class
            // object, a resource) that can appear inside decoded arguments.
            $string = json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '(unrepresentable value)';
        }

        return Str::limit($string, self::MAX_VALUE_LENGTH);
    }
}
