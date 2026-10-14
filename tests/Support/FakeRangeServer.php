<?php

namespace Tests\Support;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A file server that lives inside a Guzzle handler: it answers Range requests for one body, can issue and require a claim, and
 * plays a script of failures first (a Response, or an exception to throw). Every request it saw is kept for the test to read.
 */
final class FakeRangeServer
{
    /** @var list<array{range: string, claim: string, host: string}> */
    public array $requests = [];

    /** @var list<Response|\Throwable> answered first, one per request */
    public array $script = [];

    public function __construct(
        public string $body,
        public bool $issueClaim = false,
        public string $claim = 'claim-1',
        public bool $ignoreRange = false,
    ) {}

    public function handler(): callable
    {
        return function (RequestInterface $request, array $options) {
            $this->requests[] = ['range' => $request->getHeaderLine('Range'), 'claim' => $request->getHeaderLine('X-OeParts-Claim'), 'host' => $request->getUri()->getHost()];

            $response = $this->respond($request);

            // the mock would pass a thrown error to the on_headers callback as if it were a response; curl never does
            if ($response instanceof \Throwable) {
                unset($options['on_headers']);
            }

            return (new MockHandler([$response]))($request, $options);
        };
    }

    private function respond(RequestInterface $request): Response|\Throwable
    {
        if ($this->script !== []) {
            return array_shift($this->script);
        }

        $size = strlen($this->body);

        if ($this->issueClaim && count($this->requests) > 1 && $request->getHeaderLine('X-OeParts-Claim') !== $this->claim) {
            return new Response(404, ['Content-Length' => '0']); // the uniform "no" a share link gives a wrong claim
        }

        $headers = $this->issueClaim ? ['X-OeParts-Claim' => $this->claim] : [];

        if ($this->ignoreRange) {
            return new Response(200, $headers + ['Content-Length' => (string) $size], $this->body);
        }

        if (preg_match('~^bytes=(\d+)-(\d+)$~', $request->getHeaderLine('Range'), $m) !== 1) {
            return new Response(200, $headers + ['Content-Length' => (string) $size], $this->body);
        }

        $from = (int) $m[1];
        $to = min((int) $m[2], $size - 1);

        if ($from >= $size) {
            return new Response(416, $headers + ['Content-Range' => 'bytes */'.$size, 'Content-Length' => '0']);
        }

        $slice = substr($this->body, $from, $to - $from + 1);

        return new Response(206, $headers + ['Content-Range' => "bytes {$from}-{$to}/{$size}", 'Content-Length' => (string) strlen($slice)], $slice);
    }
}
