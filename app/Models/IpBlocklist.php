<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpBlocklist extends Model
{
    public $timestamps = false;

    protected $table = 'ip_blocklists';

    protected $fillable = ['ip_address', 'reason', 'blocked_by', 'expires_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'blocked_by');
    }
}
