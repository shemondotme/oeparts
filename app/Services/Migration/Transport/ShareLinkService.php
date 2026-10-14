<?php

namespace App\Services\Migration\Transport;

use App\Models\PackageShareLink;
use App\Security\PackageAudit;
use App\Services\Instance\CanonicalHostPolicy;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * One-time download links for exported packages, served from the SOURCE site.
 *
 * A link is 256 random bits; only their sha256 is stored. The FIRST request that reaches a live link claims it with ONE atomic
 * conditional UPDATE (two simultaneous first requests: exactly one wins) and is handed a claim secret; only the holder of that
 * secret can come back for the rest of the claim window. Every way of being refused (unknown, expired, used, revoked, wrong
 * claim, wrong address) looks the same from outside; the real reason goes to the audit log only. The link gives access control
 * and anti-enumeration, not secrecy: the package itself is encrypted with a passphrase that never travels with the link.
 */
class ShareLinkService
{
    public function __construct(private readonly PackageOutbox $outbox, private readonly PackageAudit $history) {}

    /**
     * @return array{link: PackageShareLink, token: string, share: string, warning: string|null}
     *
     * @throws MigrationException package_not_found (no such package in the exports folder), lock_busy (too many live links)
     */
    public function create(string $packageName, ?int $adminId = null, ?int $ttlHours = null, ?string $boundIp = null, ?string $note = null): array
    {
        $path = $this->outbox->find($packageName);

        if ($path === null) {
            throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => $packageName]);
        }

        $this->expireOld();

        if ($this->liveCount() >= max(1, (int) config('migration.share.max_active', 5))) {
            throw new MigrationException(MigrationErrorCode::LockBusy, ['reason' => 'too many share links are active; revoke one first']);
        }

        $info = $this->info($path);
        $size = (int) filesize($path);
        $hours = min(max(1, $ttlHours ?? (int) config('migration.share.ttl_hours', 24)), max(1, (int) config('migration.share.max_ttl_hours', 168)));
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $sha256 = isset($info['sha256']) && is_string($info['sha256']) && preg_match('/^[a-f0-9]{64}$/', $info['sha256']) === 1 ? $info['sha256'] : null;

        $link = PackageShareLink::create([
            'token_hash' => hash('sha256', $token),
            'package_name' => basename($path),
            'package_size' => $size,
            'package_sha256' => $sha256,
            'state' => PackageShareLink::ACTIVE,
            'expires_at' => Carbon::now()->addHours($hours),
            'bound_ip' => $boundIp,
            'created_by_admin_id' => $adminId,
            'note' => $note,
        ]);

        $this->audit('share.created', $link, ['package' => $link->package_name, 'expires_at' => $link->expires_at->toIso8601String(), 'bound' => $boundIp !== null]);
        [$base, $warning] = $this->baseUrl();

        return ['link' => $link, 'token' => $token, 'share' => ShareString::build($base, $token, $sha256, $size, $link->package_name), 'warning' => $warning];
    }

    /**
     * Try to use a token. On success the link is claimed (first request) or the claim is confirmed (a resume).
     *
     * @return array{ok: true, link: PackageShareLink, claim: string|null}|array{ok: false, reason: string}
     */
    public function claim(string $token, ?string $claimSecret, ?string $ip): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
            return ['ok' => false, 'reason' => 'malformed token'];
        }

        $link = PackageShareLink::query()->where('token_hash', hash('sha256', $token))->first();

        if ($link === null) {
            return ['ok' => false, 'reason' => 'unknown token'];
        }

        $now = Carbon::now();

        if ($link->bound_ip !== null && $link->bound_ip !== $ip) {
            return $this->deny($link, 'wrong address');
        }

        if ($link->state === PackageShareLink::ACTIVE) {
            if ($link->expires_at->lte($now)) {
                $this->markExpired($link);

                return $this->deny($link, 'expired');
            }

            $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $won = PackageShareLink::query()
                ->whereKey($link->getKey())
                ->where('state', PackageShareLink::ACTIVE)
                ->where('expires_at', '>', $now)
                ->update([
                    'state' => PackageShareLink::CLAIMED,
                    'claim_hash' => hash('sha256', $secret),
                    'first_claimed_at' => $now,
                    'claim_expires_at' => $now->copy()->addHours(max(1, (int) config('migration.share.claim_window_hours', 12))),
                    'updated_at' => $now,
                ]);

            if ($won !== 1) {
                return $this->deny($link, 'lost the claim to another request');
            }

            $this->audit('share.claimed', $link, ['ip' => $ip]);

            return ['ok' => true, 'link' => $link->refresh(), 'claim' => $secret];
        }

        if ($link->state === PackageShareLink::CLAIMED) {
            if ($link->claim_expires_at === null || $link->claim_expires_at->lte($now)) {
                $this->markExpired($link);

                return $this->deny($link, 'claim window over');
            }

            if ($claimSecret === null || $link->claim_hash === null || ! hash_equals($link->claim_hash, hash('sha256', $claimSecret))) {
                return $this->deny($link, 'wrong claim secret');
            }

            return ['ok' => true, 'link' => $link, 'claim' => null];
        }

        return $this->deny($link, 'link is '.$link->state);
    }

    public function revoke(int $id): bool
    {
        $changed = PackageShareLink::query()->whereKey($id)->whereIn('state', [PackageShareLink::ACTIVE, PackageShareLink::CLAIMED])
            ->update(['state' => PackageShareLink::REVOKED, 'revoked_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        if ($changed === 1) {
            $link = PackageShareLink::query()->find($id);
            $link !== null && $this->audit('share.revoked', $link, ['package' => $link->package_name]);
        }

        return $changed === 1;
    }

    /** The whole file was delivered: the link is used up. */
    public function consume(PackageShareLink $link): void
    {
        $done = PackageShareLink::query()->whereKey($link->getKey())->where('state', PackageShareLink::CLAIMED)
            ->update(['state' => PackageShareLink::CONSUMED, 'consumed_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        $done === 1 && $this->audit('share.consumed', $link, ['package' => $link->package_name]);
    }

    public function addBytesServed(PackageShareLink $link, int $bytes): void
    {
        if ($bytes > 0) {
            PackageShareLink::query()->whereKey($link->getKey())->increment('bytes_served', $bytes);
        }
    }

    /** Close links whose time is over (the janitor and every create call this; a read of a late link also does). */
    public function expireOld(): int
    {
        $now = Carbon::now();

        return PackageShareLink::query()
            ->where(function ($q) use ($now): void {
                $q->where(fn ($a) => $a->where('state', PackageShareLink::ACTIVE)->where('expires_at', '<=', $now))
                    ->orWhere(fn ($b) => $b->where('state', PackageShareLink::CLAIMED)->where('claim_expires_at', '<=', $now));
            })
            ->update(['state' => PackageShareLink::EXPIRED, 'updated_at' => $now]);
    }

    /** Links that can still be used. */
    public function liveCount(): int
    {
        $now = Carbon::now();

        return PackageShareLink::query()
            ->where(fn ($q) => $q->where(fn ($a) => $a->where('state', PackageShareLink::ACTIVE)->where('expires_at', '>', $now))
                ->orWhere(fn ($b) => $b->where('state', PackageShareLink::CLAIMED)->where('claim_expires_at', '>', $now)))
            ->count();
    }

    /**
     * The address a share string starts with: the canonical host when the site has one (any other host would be redirected
     * to it), else the host of APP_URL. https always: the receiving side accepts nothing else.
     *
     * @return array{0: string, 1: string|null} the base URL and a warning when it cannot work from another site
     */
    public function baseUrl(): array
    {
        $canonical = strtolower(trim((string) settings('seo.canonical_host', '')));
        $app = (array) parse_url((string) config('app.url'));
        $host = CanonicalHostPolicy::isBare($canonical) ? $canonical : (string) ($app['host'] ?? 'localhost');
        $port = $canonical === '' && isset($app['port']) ? ':'.$app['port'] : '';
        $warning = ($app['scheme'] ?? 'https') === 'https' ? null : 'This site is set up with an http:// address; the site that receives the link accepts https:// only, so it may not be able to use it.';

        return ['https://'.$host.$port, $warning];
    }

    /** @return array<string, mixed> */
    private function info(string $path): array
    {
        $file = $path.'.json';

        if (! is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function markExpired(PackageShareLink $link): void
    {
        PackageShareLink::query()->whereKey($link->getKey())->whereIn('state', [PackageShareLink::ACTIVE, PackageShareLink::CLAIMED])
            ->update(['state' => PackageShareLink::EXPIRED, 'updated_at' => Carbon::now()]);
    }

    /** @return array{ok: false, reason: string} */
    private function deny(PackageShareLink $link, string $reason): array
    {
        PackageShareLink::query()->whereKey($link->getKey())->increment('deny_count');
        $this->audit('share.denied', $link, ['reason' => $reason]);

        return ['ok' => false, 'reason' => $reason];
    }

    /** @param  array<string, mixed>  $context */
    private function audit(string $event, PackageShareLink $link, array $context): void
    {
        try {
            // a denial carries no run id: the same link can be refused many times and every one is worth a line
            $this->history->record($event, $event === 'share.denied' ? null : 'share-'.$link->getKey(), $context + ['link' => $link->getKey()]);
        } catch (\Throwable $e) {
            Log::warning('migration: a share link event could not be written to the audit history', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
