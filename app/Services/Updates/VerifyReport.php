<?php

namespace App\Services\Updates;

/**
 * Result of post-update verification (Module 21, Chunk 3.6): one entry per check.
 * Any 'fail' means the update did not land cleanly → the FSM auto-rolls back.
 */
class VerifyReport
{
    /** @var array<int,array{key:string,status:string,message:string}> */
    public array $checks = [];

    public function add(string $key, string $status, string $message = ''): void
    {
        $this->checks[] = ['key' => $key, 'status' => $status, 'message' => $message];
    }

    public function ok(): bool
    {
        foreach ($this->checks as $c) {
            if ($c['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }

    public function firstFailure(): ?string
    {
        foreach ($this->checks as $c) {
            if ($c['status'] === 'fail') {
                return $c['message'] !== '' ? $c['message'] : $c['key'];
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return ['ok' => $this->ok(), 'checks' => $this->checks];
    }
}
