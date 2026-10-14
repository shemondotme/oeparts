<?php

/**
 * A tiny HTTPS file server for the fetcher's loopback test (run as: php https_range_server.php <address> <port> <pem>).
 * It speaks just enough HTTP to behave like the servers a package can come from: a file that honours Range, one that ignores
 * it, redirects, a body that is cut short, and an answer carrying a claim header. One request per connection.
 */
[$script, $address, $port, $pem] = $argv + [null, '127.0.0.1', '0', ''];

$context = stream_context_create(['ssl' => ['local_cert' => $pem, 'verify_peer' => false, 'allow_self_signed' => true]]);
$bind = (str_contains($address, ':') ? '['.$address.']' : $address).':'.$port;
$server = @stream_socket_server('ssl://'.$bind, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

if ($server === false) {
    fwrite(STDERR, "cannot listen on {$bind}: {$errstr}\n");
    exit(2);
}

$body = str_repeat('0123456789', 100); // 1000 bytes, the "package"

while (true) {
    $client = @stream_socket_accept($server, 60);

    if ($client === false) {
        continue;
    }

    $request = '';

    while (! str_contains($request, "\r\n\r\n")) {
        $chunk = fread($client, 4096);

        if ($chunk === '' || $chunk === false) {
            break;
        }

        $request .= $chunk;
    }

    preg_match('~^GET (\S+) HTTP~', $request, $path);
    preg_match('~^Range: bytes=(\d+)-(\d+)~mi', $request, $range);
    $path = $path[1] ?? '/';
    $host = preg_match('~^Host: (\S+)~mi', $request, $h) === 1 ? $h[1] : '';
    $send = static function (int $status, array $headers, string $payload) use ($client): void {
        $head = "HTTP/1.1 {$status} X\r\n";

        foreach ($headers + ['Connection' => 'close'] as $name => $value) {
            $head .= "{$name}: {$value}\r\n";
        }

        @fwrite($client, $head."\r\n".$payload);
    };

    switch (strtok($path, '?')) {
        case '/file':
            $from = isset($range[1]) ? (int) $range[1] : 0;
            $to = min(isset($range[2]) ? (int) $range[2] : strlen($body) - 1, strlen($body) - 1);

            if ($from >= strlen($body)) {
                $send(416, ['Content-Range' => 'bytes */'.strlen($body), 'Content-Length' => '0'], '');
                break;
            }

            $slice = substr($body, $from, $to - $from + 1);
            $send(206, ['Content-Range' => "bytes {$from}-{$to}/".strlen($body), 'Content-Length' => (string) strlen($slice), 'X-OeParts-Claim' => 'real-claim', 'X-Seen-Host' => $host, 'X-Seen-Claim' => preg_match('~^X-Client-Claim: (\S+)~mi', $request, $c) === 1 ? $c[1] : ''], $slice);
            break;

        case '/ignore-range':
            $send(200, ['Content-Length' => (string) (5 * 1024 * 1024)], str_repeat('Z', 5 * 1024 * 1024));
            break;

        case '/redirect':
            $send(302, ['Location' => 'https://'.$host.'/file', 'Content-Length' => '5'], 'moved');
            break;

        case '/redirect-http':
            $send(302, ['Location' => 'http://'.$host.'/file', 'Content-Length' => '0'], '');
            break;

        case '/cut':
            $send(206, ['Content-Range' => 'bytes 0-9/1000', 'Content-Length' => '10'], 'abcd');
            break;

        case '/gzip':
            $send(206, ['Content-Range' => 'bytes 0-3/1000', 'Content-Length' => '4', 'Content-Encoding' => 'gzip'], 'abcd');
            break;

        default:
            $send(404, ['Content-Length' => '9'], 'not found');
    }

    fclose($client);
}
