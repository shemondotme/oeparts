<?php

namespace App\Services\Updates;

/**
 * RecoveryArm (Module 21, Chunk 4.1) — the arm-flag lifecycle for the app-independent
 * Recovery Console (public/oe-recovery.php).
 *
 * The Recovery Console is OPT-IN-ARMED (LOCKED DECISION #6, rule #47): it only ever
 * operates during an update window, signalled by the presence of an `arm.flag` file
 * in the framework-independent state dir (config('updates.state_path')). The Update
 * Engine writes the flag when an apply begins (UpdateApplier::start) and removes it
 * once the install is back in a known-good state — on SUCCESS, or after a completed
 * auto-rollback. A hard failure (app may be unbootable) deliberately LEAVES the flag
 * armed, because that is precisely when an operator needs the console.
 *
 * The flag lives beside `last-swap.json` and `lock` so the console — which never boots
 * the framework — can read all three with raw filesystem calls. Writing the flag is
 * harmless without OE_RECOVERY_KEY; the console independently refuses to act unless the
 * key is configured, so this class does not gate on it (keeps the window observable).
 */
class RecoveryArm
{
    public function flagFile(): string
    {
        return $this->stateDir().'/arm.flag';
    }

    public function isArmed(): bool
    {
        return is_file($this->flagFile());
    }

    /**
     * Open the update window. Records small, non-secret context so the console can
     * describe the in-flight update (versions, history id, when, PHP, pid).
     *
     * @param  array<string,mixed>  $context
     */
    public function arm(array $context = []): void
    {
        $this->ensureDir($this->stateDir());

        $payload = array_merge([
            'armed_at'    => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'pid'         => getmypid() ?: null,
        ], $context);

        file_put_contents(
            $this->flagFile(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /** Close the update window (auto-disarm on success / completed rollback). */
    public function disarm(): void
    {
        if (is_file($this->flagFile())) {
            @unlink($this->flagFile());
        }
    }

    /** @return array<string,mixed>|null */
    public function read(): ?array
    {
        if (! is_file($this->flagFile())) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($this->flagFile()), true);

        return is_array($data) ? $data : null;
    }

    private function stateDir(): string
    {
        return rtrim((string) config('updates.state_path', storage_path('app/updates')), '/\\');
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}
