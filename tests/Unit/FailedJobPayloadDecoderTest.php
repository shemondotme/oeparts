<?php

namespace Tests\Unit;

use App\Filament\Support\FailedJobPayloadDecoder;
use App\Models\FailedJob;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FailedJobPayloadDecoder — pure string-in/array-out transform, no DB/Livewire
 * needed. Covers the safety-sensitive argument-decoding path: only plain
 * (`O:`-prefixed) serialized commands are ever unserialize()'d, always with
 * allowed_classes: false (object-injection mitigation).
 */
class FailedJobPayloadDecoderTest extends TestCase
{
    private function jobWithPayload(array $payload): FailedJob
    {
        return new FailedJob(['payload' => json_encode($payload)]);
    }

    #[Test]
    public function meta_fields_are_extracted_from_the_outer_payload(): void
    {
        $job = $this->jobWithPayload([
            'displayName' => 'App\\Jobs\\SendWelcomeEmail',
            'maxTries' => 3,
            'timeout' => 60,
        ]);

        $decoded = FailedJobPayloadDecoder::decode($job);

        $this->assertSame('App\\Jobs\\SendWelcomeEmail', $decoded['meta']['Job']);
        $this->assertSame(3, $decoded['meta']['Max tries']);
        $this->assertSame('60s', $decoded['meta']['Timeout']);
    }

    #[Test]
    public function a_plain_serialized_command_decodes_to_a_property_list(): void
    {
        // A real serialize() call, not a hand-crafted string — exercises the
        // actual PHP serialization format the decoder has to parse.
        $command = serialize((object) ['orderId' => 42, 'email' => 'test@example.com']);

        $job = $this->jobWithPayload([
            'displayName' => 'App\\Jobs\\ProcessOrderWebhook',
            'data' => ['command' => $command],
        ]);

        $decoded = FailedJobPayloadDecoder::decode($job);

        $this->assertNull($decoded['argumentsError']);
        $this->assertNotNull($decoded['arguments']);
        $this->assertSame('42', $decoded['arguments']['orderId']);
        $this->assertSame('test@example.com', $decoded['arguments']['email']);
    }

    #[Test]
    public function an_encrypted_looking_command_is_never_unserialized(): void
    {
        $job = $this->jobWithPayload([
            'data' => ['command' => 'eyJpdiI6InJhbmRvbSIsInZhbHVlIjoiZW5jcnlwdGVkIn0='], // not 'O:'-prefixed
        ]);

        $decoded = FailedJobPayloadDecoder::decode($job);

        $this->assertNull($decoded['arguments']);
        $this->assertStringContainsString('encrypted', $decoded['argumentsError']);
    }

    #[Test]
    public function a_malformed_command_string_fails_gracefully_without_throwing(): void
    {
        $job = $this->jobWithPayload([
            'data' => ['command' => 'O:this-is-not-valid-serialized-data'],
        ]);

        $decoded = FailedJobPayloadDecoder::decode($job);

        $this->assertNull($decoded['arguments']);
        $this->assertNotNull($decoded['argumentsError']);
    }

    #[Test]
    public function no_command_payload_is_reported_explicitly(): void
    {
        $job = $this->jobWithPayload(['displayName' => 'App\\Jobs\\Whatever']);

        $decoded = FailedJobPayloadDecoder::decode($job);

        $this->assertNull($decoded['arguments']);
        $this->assertSame('No command payload found.', $decoded['argumentsError']);
    }
}
