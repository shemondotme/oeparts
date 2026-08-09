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

    /**
     * The canonical bytes signed/verified for a git-managed release. A
     * SEPARATE, additive payload/signature from payloadFor()/verifyManifest()
     * — never folded into the existing "{version}\n{sha256}" payload, which
     * would silently invalidate every already-issued zip-path signature the
     * moment this shipped. verifyManifest() proves the manifest came from the
     * real release key; it says nothing about what a `git checkout` actually
     * pulls down, since git mode never downloads or hashes a zip at all — a
     * compromised or re-pushed git remote can serve different code under a
     * validly-signed tag name undetected. This binds version to the exact
     * commit that tag is expected to point at.
     */
    public function gitPayloadFor(array $manifest): string
    {
        return (string) ($manifest['version'] ?? '')."\n".(string) ($manifest['git_commit_sha'] ?? '');
    }

    /**
     * Verify a release manifest's git-commit binding against the baked
     * public key. Fails CLOSED (not a warning) when enforced and the
     * manifest carries no git_commit_sha/git_signature at all — an
     * unsigned claim is no claim.
     *
     * @return array{0:bool,1:string} [ok, reason]
     */
    public function verifyGitManifest(array $manifest): array
    {
        if (! $this->enforced()) {
            return [true, 'Signature verification not enabled (no public key provisioned).'];
        }

        if (empty($manifest['git_commit_sha']) || empty($manifest['git_signature'])) {
            return [false, 'Release manifest has no signed git commit binding — cannot verify this git-managed update.'];
        }

        $ok = $this->verify(
            $this->gitPayloadFor($manifest),
            $manifest['git_signature'],
            (string) config('updates.signing.public_key')
        );

        return [$ok, $ok ? 'Git commit signature valid.' : 'Missing or invalid git commit signature.'];
    }
}
