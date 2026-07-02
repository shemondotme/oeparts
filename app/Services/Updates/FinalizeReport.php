<?php

namespace App\Services\Updates;

/**
 * Result of the post-swap boot (Module 21, Chunk 3.4): one entry per step run,
 * in order, with its status (ok | fail) and captured output.
 */
class FinalizeReport
{
    /** @var array<int,array{key:string,status:string,message:string}> */
    public array $steps = [];

    public function add(string $key, string $status, string $message = ''): void
    {
        $this->steps[] = ['key' => $key, 'status' => $status, 'message' => $message];
    }

    public function ok(): bool
    {
        foreach ($this->steps as $step) {
            if ($step['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }

    /** @return array{key:string,status:string,message:string}|null */
    public function get(string $key): ?array
    {
        foreach ($this->steps as $step) {
            if ($step['key'] === $key) {
                return $step;
            }
        }

        return null;
    }

    public function failures(): array
    {
        return array_values(array_filter($this->steps, fn ($s) => $s['status'] === 'fail'));
    }

    public function toArray(): array
    {
        return ['ok' => $this->ok(), 'steps' => $this->steps];
    }
}
