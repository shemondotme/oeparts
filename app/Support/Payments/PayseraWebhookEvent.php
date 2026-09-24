<?php

namespace App\Support\Payments;

/**
 * A Paysera Checkout Modern callback, parsed from the shapes documented in
 * Paysera's "Webhooks" guide (developers.paysera.com/guides/checkout-modern/
 * api-integration/webhooks):
 *
 *   order events (full snapshot):
 *     {"event":{"name":"amount_paid_updated|status_updated","type":"order"},
 *      "order":{"paysera_order_id","merchant_order_id","amount","amount_paid",
 *               "currency","status","created_at","updated_at","merchant_data"}}
 *
 *   payment / refund events (thin envelope):
 *     {"version":1,"event":{"type":"payment","name":"status_updated"},
 *      "order":{"paysera_order_id","merchant_order_id"},
 *      "payment":{"id","status","amount","currency"},"timestamp":...}
 *
 * Amounts are integers in minor currency units. An earlier version of this
 * integration assumed a flat {"order_id","status"} body; no real callback
 * has that shape, so every genuine delivery was rejected as "missing
 * order_id".
 */
final class PayseraWebhookEvent
{
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $name,
        public readonly ?string $payseraOrderId,
        public readonly ?string $merchantOrderId,
        public readonly ?string $orderStatus,
        public readonly ?int $amount,
        public readonly ?int $amountPaid,
        public readonly ?string $currency,
        public readonly ?string $paymentId,
        public readonly ?string $paymentStatus,
    ) {}

    public static function fromArray(array $data): self
    {
        $order = is_array($data['order'] ?? null) ? $data['order'] : [];
        $payment = is_array($data['payment'] ?? null) ? $data['payment'] : [];
        $event = is_array($data['event'] ?? null) ? $data['event'] : [];

        return new self(
            type: self::string($event['type'] ?? null),
            name: self::string($event['name'] ?? null),
            payseraOrderId: self::string($order['paysera_order_id'] ?? null),
            merchantOrderId: self::string($order['merchant_order_id'] ?? null),
            orderStatus: self::string($order['status'] ?? null),
            amount: self::minorUnits($order['amount'] ?? null),
            amountPaid: self::minorUnits($order['amount_paid'] ?? null),
            currency: self::string($order['currency'] ?? $payment['currency'] ?? null),
            paymentId: self::string($payment['id'] ?? null),
            paymentStatus: self::string($payment['status'] ?? null),
        );
    }

    public function isOrderEvent(): bool
    {
        return $this->type === 'order';
    }

    public function isPaymentEvent(): bool
    {
        return $this->type === 'payment';
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Minor-unit amounts arrive as integers, but tolerate numeric strings. */
    private static function minorUnits(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
