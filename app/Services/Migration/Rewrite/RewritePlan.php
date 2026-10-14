<?php

namespace App\Services\Migration\Rewrite;

/**
 * What to rewrite into what: the old name(s) of the site and the new address. The same plan always gives the
 * same hash, and a saved cursor is only ever continued under the plan that made it.
 */
final class RewritePlan
{
    /** @var array<int, string> */
    public readonly array $from;

    public readonly string $to;

    /**
     * @param  array<int, string>  $from  hosts or URLs the site was reached under
     */
    public function __construct(array $from, string $to)
    {
        // Validates both (throws InvalidArgumentException) and normalises them.
        $rewriter = new UrlRewriter($from, $to);

        $this->from = $rewriter->fromHosts();
        $this->to = $rewriter->toScheme().'://'.$rewriter->toAuthority();
    }

    public function rewriter(): UrlRewriter
    {
        return new UrlRewriter($this->from, $this->to);
    }

    public function hash(): string
    {
        $from = $this->from;
        sort($from);

        return substr(hash('sha256', json_encode([$from, $this->to], JSON_THROW_ON_ERROR)), 0, 16);
    }

    /** @return array{from: array<int, string>, to: string, hash: string} */
    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'hash' => $this->hash()];
    }
}
