<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A one-time download link for an exported package. Never holds the token itself (only its sha256) and never a file path:
 * the file is found by NAME inside the exports folder, and the name comes from this row alone.
 *
 * @property int $id
 * @property string $token_hash
 * @property string $package_name
 * @property int $package_size
 * @property string|null $package_sha256
 * @property string $state
 * @property Carbon $expires_at
 * @property string|null $bound_ip
 * @property string|null $claim_hash
 * @property Carbon|null $claim_expires_at
 * @property Carbon|null $first_claimed_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $revoked_at
 * @property int $bytes_served
 * @property int $deny_count
 * @property int|null $created_by_admin_id
 * @property string|null $note
 */
class PackageShareLink extends Model
{
    public const ACTIVE = 'active';

    public const CLAIMED = 'claimed';

    public const CONSUMED = 'consumed';

    public const EXPIRED = 'expired';

    public const REVOKED = 'revoked';

    protected $guarded = [];

    protected $hidden = ['token_hash', 'claim_hash'];

    protected function casts(): array
    {
        return [
            'package_size' => 'integer',
            'bytes_served' => 'integer',
            'deny_count' => 'integer',
            'expires_at' => 'datetime',
            'claim_expires_at' => 'datetime',
            'first_claimed_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isLive(): bool
    {
        return in_array($this->state, [self::ACTIVE, self::CLAIMED], true);
    }
}
