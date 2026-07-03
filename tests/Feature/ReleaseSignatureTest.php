<?php

namespace Tests\Feature;

use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\ReleaseSignature;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\ReleaseKeys;
use Tests\TestCase;

/**
 * Release signature (Module 21, Chunk 6.1) — RSA-SHA256 sign/verify over a release's
 * (version + sha256), enforced against an app-baked public key so a tampered manifest
 * or zip can't be applied even if it checksums cleanly.
 */
class ReleaseSignatureTest extends TestCase
{
    private function signer(): ReleaseSignature
    {
        return new ReleaseSignature;
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'version' => '1.1.0',
            'sha256'  => hash('sha256', 'release-zip-bytes'),
        ], $overrides);
    }

    #[Test]
    public function it_signs_and_verifies_a_round_trip(): void
    {
        $signer = $this->signer();
        $payload = $signer->payloadFor($this->manifest());

        $sig = $signer->sign($payload, ReleaseKeys::PRIVATE_KEY);

        $this->assertNotSame('', $sig);
        $this->assertTrue($signer->verify($payload, $sig, ReleaseKeys::PUBLIC_KEY));
    }

    #[Test]
    public function it_rejects_a_tampered_payload_or_bad_signature(): void
    {
        $signer = $this->signer();
        $payload = $signer->payloadFor($this->manifest());
        $sig = $signer->sign($payload, ReleaseKeys::PRIVATE_KEY);

        // A changed payload (e.g. a swapped sha256) no longer verifies.
        $this->assertFalse($signer->verify($payload."\ntampered", $sig, ReleaseKeys::PUBLIC_KEY));
        // Garbage / empty signatures are rejected, not fatal.
        $this->assertFalse($signer->verify($payload, 'not-base64-::', ReleaseKeys::PUBLIC_KEY));
        $this->assertFalse($signer->verify($payload, null, ReleaseKeys::PUBLIC_KEY));
        $this->assertFalse($signer->verify($payload, $sig, null));
    }

    #[Test]
    public function verify_manifest_enforces_only_when_a_public_key_is_baked(): void
    {
        $signer = $this->signer();

        // Not provisioned → verification is skipped (opt-in rollout).
        config(['updates.signing.public_key' => null]);
        $this->assertFalse($signer->enforced());
        [$ok] = $signer->verifyManifest($this->manifest());
        $this->assertTrue($ok);

        // Provisioned → a correctly-signed manifest passes.
        config(['updates.signing.public_key' => ReleaseKeys::PUBLIC_KEY]);
        $this->assertTrue($signer->enforced());
        $manifest = $this->manifest();
        $manifest['signature'] = $signer->sign($signer->payloadFor($manifest), ReleaseKeys::PRIVATE_KEY);
        [$ok] = $signer->verifyManifest($manifest);
        $this->assertTrue($ok);

        // A manifest whose sha256 was swapped after signing fails.
        $manifest['sha256'] = hash('sha256', 'evil-zip');
        [$bad] = $signer->verifyManifest($manifest);
        $this->assertFalse($bad);
    }

    #[Test]
    public function assert_manifest_throws_only_when_enforced_and_invalid(): void
    {
        $signer = $this->signer();

        // Not enforced → no throw even without a signature.
        config(['updates.signing.public_key' => null]);
        $signer->assertManifest($this->manifest());
        $this->assertTrue(true);

        // Enforced + unsigned → hard failure.
        config(['updates.signing.public_key' => ReleaseKeys::PUBLIC_KEY]);
        $this->expectException(UpdateException::class);
        $signer->assertManifest($this->manifest());
    }
}
