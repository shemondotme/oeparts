<?php

namespace App\Services\Updates;

use App\Services\Updates\Exceptions\UpdateException;

/**
 * ReleaseSignature (Module 21, Chunk 6.1) — release authenticity via an RSA-SHA256
 * signature over the release's (version + sha256), verified against an app-baked
 * public key. SHA-256 alone proves the downloaded zip matches the manifest; the
 * signature proves the manifest itself came from the real release key — so a MITM'd
 * catalog or a tampered zip+manifest can't be applied even if it hashes cleanly.
 *
 * openssl (a required extension, guaranteed on shared hosting) — no ext-sodium
 * dependency (LOCKED DECISION #9 / rule #41). The signed payload binds version to
 * hash ("{version}\n{sha256}") so a genuine old signature can't be replayed onto a
 * different version's manifest. Verification is ENFORCED only when a public key is
 * baked (opt-in rollout); the architecture was signature-ready since Chunk 0.2.
 */
class ReleaseSignature
{
    /** The canonical bytes that get signed/verified for a release manifest. */
    public function payloadFor(array $manifest): string
    {
        return (string) ($manifest['version'] ?? '')."\n".(string) ($manifest['sha256'] ?? '');
    }

    /** Sign $payload with a PEM private key → base64 signature (build/CI side). */
    public function sign(string $payload, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new UpdateException('Invalid release signing private key.');
        }

        $signature = '';
        if (! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new UpdateException('Failed to sign the release payload.');
        }

        return base64_encode($signature);
    }

    /** Verify a base64 signature over $payload against a PEM public key. */
    public function verify(string $payload, ?string $signatureB64, ?string $publicKeyPem): bool
    {
        if (empty($signatureB64) || empty($publicKeyPem)) {
            return false;
        }

        $signature = base64_decode($signatureB64, true);
        if ($signature === false) {
            return false;
        }

        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            return false;
        }

        return openssl_verify($payload, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** True when this install has a baked public key → signatures are enforced. */
    public function enforced(): bool
    {
        return trim((string) config('updates.signing.public_key')) !== '';
    }

    /**
     * Verify a release manifest against the baked public key.
     *
     * @return array{0:bool,1:string} [ok, reason]
     */
    public function verifyManifest(array $manifest): array
    {
        if (! $this->enforced()) {
            return [true, 'Signature verification not enabled (no public key provisioned).'];
        }

        $ok = $this->verify(
            $this->payloadFor($manifest),
            $manifest['signature'] ?? null,
            (string) config('updates.signing.public_key')
        );

        return [$ok, $ok ? 'Signature valid.' : 'Missing or invalid release signature.'];
    }

    /** Enforce the manifest signature — throws when enforced and invalid (hard gate). */
    public function assertManifest(array $manifest): void
    {
        [$ok, $reason] = $this->verifyManifest($manifest);
        if (! $ok) {
            throw new UpdateException('Release signature check failed: '.$reason);
        }
    }
}
