<?php

namespace App\Services\Push;

use App\Models\PushTopic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Catalogue of push-notification kinds ("topics").
 *
 * Built-in topics ship with sensible defaults (label, group, urgency,
 * on/off). Every topic lives in the push_topics table so admins can change
 * it from the panel. A topic key nobody has seen before — e.g. a notification
 * type added to the code later — is auto-registered the first time it is sent
 * (is_auto = true), so it shows up in the control panel on its own and never
 * needs a code change or a hand-edited list.
 */
class PushTopicRegistry
{
    public const FALLBACK = 'general';

    /**
     * key => [label, group, urgency, push_enabled, description]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool, 4: string}>
     */
    public static function builtin(): array
    {
        return [
            'new_order' => ['New order placed', 'Orders', 'normal', true, 'A customer places a new order.'],
            'refund_requested' => ['Refund requested', 'Orders', 'urgent', true, 'A customer asks for a refund.'],
            'payment_dispute' => ['Payment dispute / chargeback', 'Orders', 'urgent', true, 'A payment gateway reports a dispute or chargeback.'],
            'contact_message' => ['New contact message', 'Customers', 'normal', true, 'Someone writes to you through the contact form.'],
            'part_inquiry' => ['New part inquiry', 'Customers', 'normal', true, 'A customer asks about a part that is not in the catalogue.'],
            'job_failed' => ['Queue job failed', 'System', 'normal', true, 'A background job failed.'],
            'health_alert' => ['System health warning', 'System', 'urgent', true, 'A health check found a problem.'],
            'cache_alert' => ['Cache warning', 'System', 'normal', true, 'The cache is misbehaving or running out of room.'],
            'task_failed' => ['Background task failed', 'System', 'normal', true, 'Sitemap, IndexNow, imports, bulk updates or image processing failed.'],
            'task_completed' => ['Background task finished', 'System', 'normal', false, 'A background task you started has finished (off by default).'],
            self::FALLBACK => ['Other notifications', 'General', 'normal', true, 'Anything without a more specific kind.'],
        ];
    }

    /** Make sure every built-in topic has a row (idempotent, never overwrites edits). */
    public function syncBuiltin(): void
    {
        $sort = 0;

        foreach (self::builtin() as $key => [$label, $group, $urgency, $enabled, $description]) {
            $sort += 10;

            PushTopic::firstOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'group' => $group,
                    'description' => $description,
                    'urgency' => $urgency,
                    'push_enabled' => $enabled,
                    'sound_enabled' => true,
                    'is_auto' => false,
                    'sort' => $sort,
                ],
            );
        }

    }

    /** Find a topic, creating it (built-in default or auto-discovered) if missing. */
    public function find(?string $key): PushTopic
    {
        $key = $this->normalize($key);

        $topic = PushTopic::query()->where('key', $key)->first();

        if ($topic) {
            return $topic;
        }

        $builtin = self::builtin()[$key] ?? null;

        try {
            return PushTopic::create($builtin
                ? [
                    'key' => $key, 'label' => $builtin[0], 'group' => $builtin[1],
                    'description' => $builtin[4], 'urgency' => $builtin[2],
                    'push_enabled' => $builtin[3], 'sound_enabled' => true,
                    'is_auto' => false, 'sort' => 100,
                ]
                : [
                    'key' => $key,
                    'label' => Str::headline($key),
                    'group' => 'Discovered',
                    'description' => 'Added automatically the first time this notification was sent.',
                    'urgency' => 'normal', 'push_enabled' => true, 'sound_enabled' => true,
                    'is_auto' => true, 'sort' => 900,
                ]);
        } catch (\Throwable $e) {
            // Lost a creation race with a parallel worker — the row exists now.
            $existing = PushTopic::query()->where('key', $key)->first();

            if ($existing) {
                return $existing;
            }

            Log::warning('PushTopicRegistry: could not register topic', ['key' => $key, 'error' => $e->getMessage()]);

            return new PushTopic([
                'key' => $key, 'label' => Str::headline($key), 'group' => 'Discovered',
                'urgency' => 'normal', 'push_enabled' => true, 'sound_enabled' => true,
            ]);
        }
    }

    public function normalize(?string $key): string
    {
        $key = Str::of((string) $key)->lower()->replaceMatches('/[^a-z0-9_\-]+/', '_')->trim('_')->limit(100, '')->toString();

        return $key === '' ? self::FALLBACK : $key;
    }
}
