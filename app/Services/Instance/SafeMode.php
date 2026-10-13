<?php

namespace App\Services\Instance;

use Illuminate\Support\Facades\Log;

/**
 * The one place that knows what this installation may do given what it is (OE_MODE).
 *
 * It reads only config('instance.*') and the ledger file. It must never call settings() or CacheService:
 * those are the very things it masks, and the application's settings service asks it first, so a lookup
 * from here would loop. It is also cheap enough to be asked on every request.
 *
 * Production is the default and behaves exactly as an installation that never heard of modes. A value that
 * is not one of the three modes (a typo) is treated as quarantine, the strictest, and logged as critical
 * once: a mistake must never turn a copy back into the real shop.
 */
final class SafeMode
{
    public const MAIL_SEND = 'send';

    public const MAIL_DROP = 'drop';

    /** Characters of an order or invoice number prefix a copy may use in total. */
    private const PREFIX_LIMIT = 30;

    private static bool $badValueLogged = false;

    public function __construct(private readonly ?InstanceState $state = null) {}

    /** Forget that a bad value was already logged (tests). */
    public static function resetLogMemory(): void
    {
        self::$badValueLogged = false;
    }

    public function mode(): InstanceMode
    {
        $raw = $this->rawValue();

        if ($raw === '') {
            return InstanceMode::Production;
        }

        $mode = InstanceMode::tryFrom($raw);

        if ($mode !== null) {
            return $mode;
        }

        if (! self::$badValueLogged) {
            self::$badValueLogged = true;
            Log::critical("OE_MODE is \"{$raw}\", which is not one of production, staging or quarantine. This site is being run as quarantine until that is fixed.");
        }

        return InstanceMode::Quarantine;
    }

    /** True when OE_MODE holds something that is not a mode at all. */
    public function hasInvalidValue(): bool
    {
        $raw = $this->rawValue();

        return $raw !== '' && InstanceMode::tryFrom($raw) === null;
    }

    public function isProduction(): bool
    {
        return $this->mode()->isProduction();
    }

    public function isCopy(): bool
    {
        return $this->mode()->isCopy();
    }

    public function allows(Capability $capability): bool
    {
        return match ($this->mode()) {
            InstanceMode::Production => true,
            InstanceMode::Staging => match ($capability) {
                Capability::ChargeCard, Capability::SettlePayment, Capability::RunInternalSchedule => true,
                default => false,
            },
            InstanceMode::Quarantine => false,
        };
    }

    /** @throws InstanceModeBlockedException */
    public function assertAllowed(Capability $capability, string $detail = ''): void
    {
        if (! $this->allows($capability)) {
            throw new InstanceModeBlockedException($capability, $this->mode(), $detail);
        }
    }

    /**
     * May this payment action go ahead? A copy refuses Paysera outright (no sandbox exists), and refuses to
     * capture or release a payment that was copied from the real shop (its id is at or below the watermark):
     * that would move real money for an order that only exists as a test.
     *
     * @throws InstanceModeBlockedException
     */
    public function assertPaymentAllowed(Capability $capability, ?int $paymentId = null): void
    {
        if ($this->isProduction()) {
            return;
        }

        $this->assertAllowed($capability);

        if ($capability === Capability::SettlePayment && $paymentId !== null && $this->isCopiedPayment($paymentId)) {
            throw new InstanceModeBlockedException($capability, $this->mode(), 'This payment was copied from the real shop.');
        }
    }

    public function isCopiedPayment(int $paymentId): bool
    {
        if ($this->isProduction()) {
            return false;
        }

        $watermark = ($this->state ?? new InstanceState)->watermark('payments');

        return $watermark !== null && $paymentId <= $watermark;
    }

    /** Whether a scheduled command of this class (see config/instance.php) may run in this mode. */
    public function scheduledAllowed(string $class): bool
    {
        return match ($this->mode()) {
            InstanceMode::Production => true,
            InstanceMode::Staging => in_array($class, [SystemGate::ALWAYS, SystemGate::INTERNAL], true),
            InstanceMode::Quarantine => $class === SystemGate::ALWAYS,
        };
    }

    /** May the web "cron fallback" run the scheduler? A copy needs OE_NONPROD_HTTP_CRON=true. */
    public function httpCronAllowed(): bool
    {
        return $this->isProduction() || (bool) config('instance.nonprod_http_cron', false);
    }

    /** The robots.txt line that keeps crawlers out, or null on the real shop. */
    public function robotsDirective(): ?string
    {
        return $this->isCopy() ? "User-agent: *\nDisallow: /" : null;
    }

    /** Order and invoice numbers on a copy are visibly test numbers, and never longer than the limit. */
    public function sequencePrefix(string $real): string
    {
        if ($this->isProduction()) {
            return $real;
        }

        $marker = (string) config('instance.sequence_prefix', 'T-');

        return $marker.substr($real, 0, max(0, self::PREFIX_LIMIT - strlen($marker)));
    }

    /**
     * Should mail to this address be delivered? Production: yes. Quarantine: never. Staging: only to the
     * allow-list (exact address or "@domain"), everything else is dropped.
     *
     * @return 'send'|'drop'
     */
    public function mailVerdict(string $address): string
    {
        return match ($this->mode()) {
            InstanceMode::Production => self::MAIL_SEND,
            InstanceMode::Quarantine => self::MAIL_DROP,
            InstanceMode::Staging => $this->isAllowListed($address) ? self::MAIL_SEND : self::MAIL_DROP,
        };
    }

    /** Text put in front of the subject of a mail a staging copy does deliver. */
    public function mailSubjectPrefix(): string
    {
        return $this->mode() === InstanceMode::Staging ? '[STAGING] ' : '';
    }

    /** @return array<int, string> */
    public function mailAllowList(): array
    {
        $raw = (string) config('instance.mail.allowlist', '');

        return array_values(array_filter(array_map(static fn (string $item): string => strtolower(trim($item)), explode(',', $raw)), static fn (string $item): bool => $item !== ''));
    }

    /**
     * Make the mail system itself harmless on a copy: nothing is handed to a real SMTP server.
     * (A database-stored SMTP login would otherwise still be applied on top.)
     */
    public function applyToConfig(): void
    {
        if ($this->isCopy()) {
            config(['mail.default' => 'log']);
        }
    }

    /**
     * What a copy shows instead of the stored value of one settings group. The stored values are never
     * changed. On the real shop this returns the values untouched.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function maskSettings(string $group, array $values): array
    {
        if ($this->isProduction()) {
            return $values;
        }

        foreach ($this->overridesFor($group) as $key => $replacement) {
            if (str_ends_with($key, '*')) {
                $prefix = substr($key, 0, -1);

                foreach (array_keys($values) as $existing) {
                    if (str_starts_with((string) $existing, $prefix)) {
                        $values[$existing] = $replacement;
                    }
                }

                continue;
            }

            if (array_key_exists($key, $values)) {
                $values[$key] = $replacement;
            }
        }

        return $values;
    }

    /** @return array<string, mixed> key => the value a copy sees, for one group */
    public function overridesFor(string $group): array
    {
        return (array) (config('instance.overrides', [])[$group] ?? []);
    }

    /**
     * Every setting this copy is hiding, for the admin notice "Safe mode is masking N settings".
     *
     * @return array<int, array{group: string, key: string, shows: mixed}>
     */
    public function activeOverrides(): array
    {
        if ($this->isProduction()) {
            return [];
        }

        $list = [];

        foreach ((array) config('instance.overrides', []) as $group => $keys) {
            foreach ((array) $keys as $key => $shows) {
                $list[] = ['group' => (string) $group, 'key' => (string) $key, 'shows' => $shows];
            }
        }

        return $list;
    }

    private function rawValue(): string
    {
        return strtolower(trim((string) config('instance.mode', '')));
    }

    private function isAllowListed(string $address): bool
    {
        $address = strtolower(trim($address));

        foreach ($this->mailAllowList() as $rule) {
            if ($rule === $address || (str_starts_with($rule, '@') && str_ends_with($address, $rule))) {
                return true;
            }
        }

        return false;
    }
}
