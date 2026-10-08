<?php

use App\Notifications\AdminDashboardNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin bell notifications created from the scheduler/queue (CLI) stored
 * absolute URLs built from APP_URL, so "View" opened http://localhost/... on
 * the live site. AdminDashboardNotification now stores host-relative links;
 * this rewrites the ones already in the table so they stop pointing at
 * localhost too. Rows without an absolute URL are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('type', AdminDashboardNotification::class)
            ->where('data', 'like', '%http%')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $data = json_decode($row->data, true);

                    if (! is_array($data)) {
                        continue;
                    }

                    $changed = false;

                    if (isset($data['action_url']) && is_string($data['action_url'])) {
                        $relative = self::relative($data['action_url']);
                        $changed = $changed || $relative !== $data['action_url'];
                        $data['action_url'] = $relative;
                    }

                    foreach ($data['actions'] ?? [] as $i => $action) {
                        if (isset($action['url']) && is_string($action['url'])) {
                            $relative = self::relative($action['url']);
                            $changed = $changed || $relative !== $action['url'];
                            $data['actions'][$i]['url'] = $relative;
                        }
                    }

                    if ($changed) {
                        DB::table('notifications')->where('id', $row->id)->update([
                            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        // Not reversible: the original host is gone, and it was the bug.
    }

    private static function relative(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $path = ($parts['path'] ?? '') !== '' ? $parts['path'] : '/';

        return $path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
};
