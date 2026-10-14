<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;

/**
 * The only way this feature reaches the internet (invariant I3). One call fetches ONE bounded byte range of a file:
 *
 *   - every hop (the URL and each redirect) goes through UrlPolicy; the validated address is pinned with CURLOPT_RESOLVE, so
 *     the name cannot point somewhere else between the check and the connection; the certificate is still checked against
 *     the host name;
 *   - https only, no proxy, no automatic redirects (at most three, each one validated and pinned again), no content encoding;
 *   - the body is accepted only when the headers prove it is the range that was asked for: a 200 to a range request is
 *     refused before a byte is written, Content-Range must start exactly where our offset is, and the sink refuses the
 *     first byte past what was asked for;
 *   - the claim header and the other answers are handed to the caller before the first body byte.
 *
 * Guzzle with the curl handler directly, not the Laravel Http facade: a streamed request there selects the stream handler,
 * where the curl options do not exist.
 */
class SafeHttpFetcher
{
    public const MAX_REDIRECTS = 3;

    private const REDIRECTS = [301, 302, 303, 307, 308];

    private const RETRYABLE_STATUS = [408, 425, 429, 500, 502, 503, 504];

    /** libcurl codes of a failed TLS handshake or certificate check (35, 51, 58, 59, 60, 77, 83). */
    private const TLS_ERRORS = [35, 51, 58, 59, 60, 77, 83];

    private const KEPT_HEADERS = ['x-oeparts-claim', 'etag', 'last-modified', 'content-type', 'retry-after'];

    private readonly ClientInterface $client;

    /** @param  callable|null  $handler a Guzzle handler (tests pass a MockHandler); the real curl handler by default */
    public function __construct(private readonly UrlPolicy $policy, ?callable $handler = null)
    {
        $this->client = new Client(['handler' => HandlerStack::create($handler ?? new CurlHandler)]);
    }

    /** Whether trying the same request again later can help (a timeout, a cut connection, a busy or failing server). */
    public static function isRetryable(MigrationException $e): bool
    {
        if ($e->errorCode === MigrationErrorCode::UrlTimeout) {
            return true;
        }

        if ($e->errorCode !== MigrationErrorCode::UrlHttpError) {
            return false;
        }

        return in_array($e->params['status'] ?? 0, self::RETRYABLE_STATUS, true) || in_array($e->params['reason'] ?? '', ['transport', 'cut_short'], true);
    }

    /**
     * @param  resource  $sink  open for writing and already positioned where this range belongs
     * @param  array{headers?: array<string, string>, on_headers?: callable(FetchHeaders): void, timeout?: int}  $options
     *
     * @throws MigrationException url_not_https, url_blocked, url_redirect_blocked, url_too_large, url_timeout, url_http_error
     */
    public function fetchRange(string $url, $sink, int $offset, int $length, array $options = []): FetchResponse
    {
        $current = $url;
        $firstHost = null;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            try {
                $target = $this->policy->validate($current);
            } catch (MigrationException $e) {
                throw $hop === 0 ? $e : new MigrationException(MigrationErrorCode::UrlRedirectBlocked, ['reason' => (string) ($e->params['reason'] ?? $e->errorCode->value)], $e);
            }

            $firstHost ??= $target->host;
            // the claim and any other custom header go to the host the caller named, never to another one
            $headers = $target->host === $firstHost ? (array) ($options['headers'] ?? []) : [];

            [$response, $location] = $this->once($target, $sink, $offset, $length, $headers, $options, $hop);

            if ($location === null) {
                return $response;
            }

            $current = (string) UriResolver::resolve(new Uri($target->url), new Uri($location));
        }

        throw new MigrationException(MigrationErrorCode::UrlRedirectBlocked, ['reason' => 'too many redirects']);
    }

    /**
     * @param  array<string, string>  $extraHeaders
     * @param  array<string, mixed>  $options
     * @return array{0: FetchResponse, 1: string|null} the response, and the Location to follow when it was a redirect
     */
    private function once(ValidatedUrl $target, $sink, int $offset, int $length, array $extraHeaders, array $options, int $hop): array
    {
        $gate = new GatedSink($sink);
        $meta = ['status' => 0, 'headers' => [], 'total' => null, 'start' => null, 'end' => null, 'location' => null];
        $callback = $options['on_headers'] ?? null;

        $onHeaders = function (ResponseInterface $response) use (&$meta, $gate, $offset, $length, $callback, $target): void {
            $status = $response->getStatusCode();
            $meta['status'] = $status;

            foreach (self::KEPT_HEADERS as $name) {
                $meta['headers'][$name] = $response->getHeaderLine($name);
            }

            if (in_array($status, self::REDIRECTS, true)) {
                $meta['location'] = $response->getHeaderLine('Location');

                return;
            }

            if ($status === 416) {
                $meta['total'] = preg_match('~^bytes \*/(\d+)$~', $response->getHeaderLine('Content-Range'), $m) === 1 ? (int) $m[1] : null;

                return;
            }

            if ($status !== 200 && $status !== 206) {
                $retryAfter = $response->getHeaderLine('Retry-After');

                throw new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => $status, 'reason' => 'status', 'retry_after' => ctype_digit($retryAfter) ? (int) $retryAfter : null]);
            }

            $encoding = strtolower(trim($response->getHeaderLine('Content-Encoding')));

            if ($encoding !== '' && $encoding !== 'identity') {
                throw new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => $status, 'reason' => 'encoded']);
            }

            $contentLength = $response->hasHeader('Content-Length') && ctype_digit($response->getHeaderLine('Content-Length')) ? (int) $response->getHeaderLine('Content-Length') : null;

            if ($status === 206) {
                if (preg_match('~^bytes (\d+)-(\d+)/(\d+|\*)$~', $response->getHeaderLine('Content-Range'), $m) !== 1 || (int) $m[1] !== $offset || (int) $m[2] < (int) $m[1]) {
                    throw new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => $status, 'reason' => 'range_mismatch']);
                }

                [$start, $end] = [(int) $m[1], (int) $m[2]];
                $expected = $end - $start + 1;

                if ($expected > $length) {
                    throw new MigrationException(MigrationErrorCode::UrlTooLarge, ['reason' => 'more than the range asked for']);
                }

                if ($contentLength !== null && $contentLength !== $expected) {
                    throw new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => $status, 'reason' => 'range_mismatch']);
                }

                $meta['total'] = $m[3] === '*' ? null : (int) $m[3];
                [$meta['start'], $meta['end']] = [$start, $end];
            } else {
                // A server that ignores the range answers 200 with the whole file. That is safe only when the whole file is
                // exactly what one range could hold; otherwise it would be an unbounded body and no resume.
                if ($offset !== 0 || $contentLength === null || $contentLength > $length) {
                    throw new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => $status, 'reason' => 'range_ignored']);
                }

                $expected = $contentLength;
                $meta['total'] = $contentLength;
                [$meta['start'], $meta['end']] = [0, max(0, $contentLength - 1)];
            }

            if (is_callable($callback)) {
                $callback(new FetchHeaders($status, $meta['headers'], $meta['total'], $meta['start'], $meta['end'], $target->host));
            }

            $gate->open($expected);
        };

        $request = [
            'headers' => array_replace($extraHeaders, [
                'Accept-Encoding' => 'identity',
                'Range' => 'bytes='.$offset.'-'.($offset + $length - 1),
                'User-Agent' => 'OeParts-Migration/1',
                'Accept' => 'application/octet-stream',
            ]),
            'allow_redirects' => false,
            'http_errors' => false,
            'decode_content' => false,
            'protocols' => ['https'],
            'proxy' => '',
            'verify' => $options['verify'] ?? true,
            'sink' => $gate,
            'on_headers' => $onHeaders,
            'timeout' => (int) ($options['timeout'] ?? 300),
            'connect_timeout' => 15,
            // No CURLOPT_MAXFILESIZE_LARGE: it fires before the headers are examined and would turn a 404 page into "too
            // large". The headers check and the sink enforce the bound exactly, with the true reason.
            'curl' => [
                CURLOPT_RESOLVE => [$target->host.':'.$target->port.':'.(str_contains($target->pinnedAddress, ':') ? '['.$target->pinnedAddress.']' : $target->pinnedAddress)],
                CURLOPT_LOW_SPEED_LIMIT => 1024,
                CURLOPT_LOW_SPEED_TIME => 30,
            ],
        ];

        try {
            $this->client->request('GET', $target->url, $request);
        } catch (\Throwable $e) {
            throw $this->translate($e, $gate);
        }

        if ($gate->exceeded) {
            throw new MigrationException(MigrationErrorCode::UrlTooLarge, ['reason' => 'more bytes than the range asked for']);
        }

        $status = $meta['status'];

        if (in_array($status, self::REDIRECTS, true)) {
            if ((string) $meta['location'] === '') {
                throw new MigrationException(MigrationErrorCode::UrlRedirectBlocked, ['reason' => 'redirect without a location']);
            }

            return [new FetchResponse($status, 0, null, null, null, $meta['headers'], $target->url, $hop), (string) $meta['location']];
        }

        if (($status === 200 || $status === 206) && $meta['start'] !== null && $gate->written !== $meta['end'] - $meta['start'] + 1) {
            throw new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => $status, 'reason' => 'cut_short']);
        }

        return [new FetchResponse($status, $gate->written, $meta['total'], $meta['start'], $meta['end'], $meta['headers'], $target->url, $hop), null];
    }

    /** A transport failure in the words of the catalogue. A MigrationException thrown by a check on the way is passed through. */
    private function translate(\Throwable $e, GatedSink $gate): MigrationException
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof MigrationException) {
                return $cause;
            }
        }

        if ($gate->exceeded) {
            return new MigrationException(MigrationErrorCode::UrlTooLarge, ['reason' => 'more bytes than the range asked for'], $e);
        }

        $errno = ($e instanceof RequestException || $e instanceof ConnectException) ? (int) ($e->getHandlerContext()['errno'] ?? 0) : 0;

        return match (true) {
            $errno === CURLE_OPERATION_TIMEDOUT => new MigrationException(MigrationErrorCode::UrlTimeout, [], $e),
            $errno === CURLE_FILESIZE_EXCEEDED => new MigrationException(MigrationErrorCode::UrlTooLarge, ['reason' => 'file too large'], $e),
            $errno === CURLE_PARTIAL_FILE => new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => 0, 'reason' => 'cut_short'], $e),
            in_array($errno, self::TLS_ERRORS, true) => new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => 0, 'reason' => 'tls', 'errno' => $errno], $e),
            default => new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => 0, 'reason' => 'transport', 'errno' => $errno], $e),
        };
    }
}
