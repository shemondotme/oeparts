<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'failed_at' => 'datetime',
    ];

    /** Full class name decoded from the payload JSON (e.g. "App\Jobs\SendWelcomeEmail"), or 'Unknown'. */
    public function jobClassFqcn(): string
    {
        $payload = json_decode($this->payload ?? '{}', true);

        return $payload['displayName'] ?? $payload['job'] ?? 'Unknown';
    }

    /** Short class name for display (e.g. "SendWelcomeEmail"). */
    public function jobClassName(): string
    {
        return class_basename($this->jobClassFqcn());
    }
}
