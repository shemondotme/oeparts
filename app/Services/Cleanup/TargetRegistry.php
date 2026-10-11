<?php

namespace App\Services\Cleanup;

use App\Services\Cleanup\Contracts\CleanupTarget;
use InvalidArgumentException;

/**
 * The allow-list of everything the Cleanup Engine can act on.
 *
 * A request can only ever name a target KEY; that key must resolve here. There is no code path
 * from user input to a table name or a filesystem path, which is what makes the destructive
 * parts of the engine safe to expose behind a button.
 *
 * Targets are listed in config('cleanup.targets'). Business data is additionally protected by a
 * hard deny-list of tables: even a coding mistake that registers one of them is refused when the
 * target is constructed.
 */
class TargetRegistry
{
    /**
     * Tables that are business records or the audit/lock trail of one — never a cleanup target,
     * not even by mistake. order_status_history in particular is read by AutoCompleteShippedOrders
     * (the "shipped N days ago" rule), so pruning it would change business behaviour.
     */
    public const NEVER_PRUNE = [
        'orders', 'order_items', 'order_status_history', 'invoices', 'custom_invoices',
        'custom_invoice_payments', 'payments', 'payment_transactions', 'refund_requests',
        'users', 'admins', 'products', 'product_images', 'manufacturers', 'car_models',
        'coupons', 'coupon_usages', 'carts', 'cart_items', 'settings', 'migrations',
        'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        'languages', 'language_strings', 'pages', 'blog_posts', 'media_files',
        'contact_messages', 'part_inquiries', 'newsletter_subscribers',
    ];

    /** @var array<string, CleanupTarget>|null */
    private ?array $targets = null;

    /**
     * @param  list<class-string<CleanupTarget>>|null  $classes  override for tests
     */
    public function __construct(private readonly ?array $classes = null) {}

    /**
     * @throws InvalidArgumentException when a business table is passed
     */
    public static function assertTableAllowed(string $table): void
    {
        if (in_array($table, self::NEVER_PRUNE, true)) {
            throw new InvalidArgumentException("Table '{$table}' holds business records and can never be a cleanup target.");
        }
    }

    /** @return array<string, CleanupTarget> keyed by target key, in registration order */
    public function all(): array
    {
        if ($this->targets === null) {
            $this->targets = [];

            foreach ($this->classes ?? (array) config('cleanup.targets', []) as $class) {
                /** @var CleanupTarget $target */
                $target = app($class);

                if (isset($this->targets[$target->key()])) {
                    throw new InvalidArgumentException("Duplicate cleanup target key '{$target->key()}'.");
                }

                $this->targets[$target->key()] = $target;
            }
        }

        return $this->targets;
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * @throws InvalidArgumentException for any key that is not registered
     */
    public function get(string $key): CleanupTarget
    {
        return $this->all()[$key]
            ?? throw new InvalidArgumentException("Unknown cleanup target '{$key}'.");
    }

    /** @return array<string, CleanupTarget> */
    public function inGroup(string $group): array
    {
        return array_filter($this->all(), fn (CleanupTarget $t) => $t->group() === $group);
    }

    /**
     * The retention (days) actually applied: the admin's setting, never below the target's floor.
     * Enforced here so a direct database edit of the setting cannot break the floor either.
     */
    public function retentionDays(CleanupTarget $target): int
    {
        $configured = (int) settings("cleanup.retention_{$target->key()}", $target->defaultDays());

        return (new OverDeleteGuard)->effectiveDays($configured, $target->floorDays(), $target->defaultDays());
    }

    /** Whether the scheduled run includes this target. */
    public function autoEnabled(CleanupTarget $target): bool
    {
        if ($target->reportOnly()) {
            return false;
        }

        $value = settings("cleanup.auto_{$target->key()}");

        return $value === null ? $target->autoByDefault() : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
