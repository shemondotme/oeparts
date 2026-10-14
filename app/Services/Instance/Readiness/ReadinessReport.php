<?php

namespace App\Services\Instance\Readiness;

/**
 * The launch-readiness report: every check that has run, and what can be done with it.
 *
 * A site may leave quarantine, or a copy may go live, only when `canRelease()`: no failure that is not waived, nothing still
 * pending. A waiver is a typed reason for ONE overridable check (kept in the ledger by whoever grants it); a failure that is not
 * marked overridable can never be waived.
 */
final class ReadinessReport
{
    /**
     * @param  array<int, ReadinessCheck>  $checks
     * @param  array<int, string>  $phases  the phases this report covers
     */
    public function __construct(public readonly array $checks, public readonly bool $complete, public readonly string $generatedAt, public readonly array $phases = [ReadinessProbe::STATIC, ReadinessProbe::LIVE]) {}

    /** True when both the static and the live checks are in the report: a report of only the static ones can never release a site. */
    public function coversEverything(): bool
    {
        return in_array(ReadinessProbe::STATIC, $this->phases, true) && in_array(ReadinessProbe::LIVE, $this->phases, true);
    }

    /** @return array<string, int> status => how many */
    public function counts(): array
    {
        $counts = [ReadinessCheck::PASS => 0, ReadinessCheck::WARN => 0, ReadinessCheck::FAIL => 0, ReadinessCheck::SKIP => 0, ReadinessCheck::PENDING => 0];

        foreach ($this->checks as $check) {
            $counts[$check->status] = ($counts[$check->status] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return array<int, ReadinessCheck> */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, static fn (ReadinessCheck $c): bool => $c->isFail()));
    }

    public function find(string $key): ?ReadinessCheck
    {
        foreach ($this->checks as $check) {
            if ($check->key === $key) {
                return $check;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $waived  keys of overridable checks a person has waived
     */
    public function canRelease(array $waived = []): bool
    {
        if (! $this->complete || ! $this->coversEverything()) {
            return false;
        }

        foreach ($this->checks as $check) {
            if ($check->status === ReadinessCheck::PENDING) {
                return false;
            }

            if ($check->isFail() && ! ($check->overridable && in_array($check->key, $waived, true))) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => 1, 'generated_at' => $this->generatedAt, 'complete' => $this->complete, 'phases' => $this->phases,
            'counts' => $this->counts(), 'checks' => array_map(static fn (ReadinessCheck $c): array => $c->toArray(), $this->checks),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            array_map(static fn (array $c): ReadinessCheck => ReadinessCheck::fromArray($c), (array) ($data['checks'] ?? [])),
            (bool) ($data['complete'] ?? false), (string) ($data['generated_at'] ?? ''), array_values(array_map('strval', (array) ($data['phases'] ?? [ReadinessProbe::STATIC, ReadinessProbe::LIVE]))),
        );
    }
}
