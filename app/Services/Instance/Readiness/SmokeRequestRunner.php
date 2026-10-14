<?php

namespace App\Services\Instance\Readiness;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Asks the site for a few of its own pages, the way a visitor and a crawler would, and reports what came back.
 *
 * Two ways to ask. If `instance.readiness.loopback_base` is set (for example http://127.0.0.1) the request goes out over the
 * network to that address with the site's own host in the Host header: that is the real thing, through the real web server. Without
 * it the request is run IN THIS PROCESS through the application's HTTP kernel: the application answers exactly as it would, but the
 * web server in front of it is not part of the test, so the report says the check was partial.
 *
 * The transport can be replaced (tests give it canned answers); the page text is cut at 256 KB.
 */
class SmokeRequestRunner
{
    private const BODY_LIMIT = 262_144;

    /** @param  (\Closure(string, string): SmokeResult)|null  $transport  (host, path) => result */
    public function __construct(private readonly ?\Closure $transport = null) {}

    /** True when the request goes through a real network round trip, false when it is run inside this process. */
    public function isLoopback(): bool
    {
        return $this->transport === null && (string) config('instance.readiness.loopback_base', '') !== '';
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, SmokeResult>
     */
    public function run(string $host, array $paths): array
    {
        return array_map(fn (string $path): SmokeResult => $this->fetch($host, $path), $paths);
    }

    public function fetch(string $host, string $path): SmokeResult
    {
        if ($this->transport !== null) {
            return ($this->transport)($host, $path);
        }

        try {
            return $this->isLoopback() ? $this->overTheNetwork($host, $path) : $this->inProcess($host, $path);
        } catch (\Throwable $e) {
            return new SmokeResult($path, 0, error: $e->getMessage());
        }
    }

    /**
     * Follow redirects (to the same host or another) up to `$max` hops and say whether the chain loops back on itself.
     *
     * @return array{loop: bool, hops: int, last: SmokeResult}
     */
    public function follow(string $host, string $path, int $max = 6): array
    {
        $seen = [];
        $current = $this->fetch($host, $path);
        $hops = 0;

        while ($current->isRedirect() && $hops < $max) {
            $key = strtolower((string) $current->location);

            if (isset($seen[$key])) {
                return ['loop' => true, 'hops' => $hops, 'last' => $current];
            }

            $seen[$key] = true;
            $next = parse_url((string) $current->location);
            $nextHost = strtolower((string) ($next['host'] ?? $host));

            if ($nextHost !== strtolower($host)) {
                break; // it left this site: the caller's concern, not a loop
            }

            $current = $this->fetch($host, ($next['path'] ?? '/').(isset($next['query']) ? '?'.$next['query'] : ''));
            $hops++;
        }

        return ['loop' => $current->isRedirect() && $hops >= $max, 'hops' => $hops, 'last' => $current];
    }

    private function overTheNetwork(string $host, string $path): SmokeResult
    {
        $response = Http::withHeaders(['Host' => $host, 'User-Agent' => 'OeParts-readiness'])
            ->withoutRedirecting()->timeout(15)->connectTimeout(5)
            ->get(rtrim((string) config('instance.readiness.loopback_base'), '/').$path);

        return new SmokeResult($path, $response->status(), $response->header('Location') ?: null, substr($response->body(), 0, self::BODY_LIMIT));
    }

    private function inProcess(string $host, string $path): SmokeResult
    {
        $original = app()->bound('request') ? app('request') : null;
        $secure = str_starts_with((string) config('app.url'), 'https://');
        $request = Request::create(($secure ? 'https://' : 'http://').$host.$path, 'GET', [], [], [], ['HTTP_HOST' => $host, 'HTTP_USER_AGENT' => 'OeParts-readiness', 'REMOTE_ADDR' => '127.0.0.1']);

        try {
            $kernel = app(Kernel::class);
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            return new SmokeResult($path, $response->getStatusCode(), $response->headers->get('Location'), substr((string) $response->getContent(), 0, self::BODY_LIMIT));
        } finally {
            if ($original !== null) {
                app()->instance('request', $original);
            }
        }
    }
}
