<?php

namespace App\Services;

/**
 * Recognises the well-known AI crawlers by their user-agent token.
 *
 * A user agent is only a claim — anyone can send "GPTBot". The counts are a good picture of who is reading the
 * site, not proof of who is.
 */
final class AiCrawlerDetector
{
    /**
     * token (as it appears in the user agent) => [display name, operator, what it does]
     * Order matters: the first token found wins, so a more specific token is listed before a generic one.
     */
    public const BOTS = [
        'OAI-SearchBot' => ['OAI-SearchBot', 'OpenAI', 'search'],
        'ChatGPT-User' => ['ChatGPT-User', 'OpenAI', 'user'],
        'GPTBot' => ['GPTBot', 'OpenAI', 'training'],
        'Claude-SearchBot' => ['Claude-SearchBot', 'Anthropic', 'search'],
        'Claude-User' => ['Claude-User', 'Anthropic', 'user'],
        'ClaudeBot' => ['ClaudeBot', 'Anthropic', 'training'],
        'anthropic-ai' => ['anthropic-ai', 'Anthropic', 'training'],
        'Perplexity-User' => ['Perplexity-User', 'Perplexity', 'user'],
        'PerplexityBot' => ['PerplexityBot', 'Perplexity', 'search'],
        'MistralAI-User' => ['MistralAI-User', 'Mistral', 'user'],
        'DuckAssistBot' => ['DuckAssistBot', 'DuckDuckGo', 'user'],
        'Applebot-Extended' => ['Applebot-Extended', 'Apple', 'training'],
        'meta-externalagent' => ['Meta-ExternalAgent', 'Meta', 'training'],
        'Bytespider' => ['Bytespider', 'ByteDance', 'training'],
        'Amazonbot' => ['Amazonbot', 'Amazon', 'training'],
        'CCBot' => ['CCBot', 'Common Crawl', 'training'],
        'cohere-ai' => ['cohere-ai', 'Cohere', 'training'],
        'Diffbot' => ['Diffbot', 'Diffbot', 'training'],
        'YouBot' => ['YouBot', 'You.com', 'search'],
        'Timpibot' => ['Timpibot', 'Timpi', 'training'],
    ];

    public const PURPOSES = [
        'training' => 'Collects text to train AI models',
        'search' => 'Builds an AI search index',
        'user' => 'Fetches a page because a person asked an assistant about it',
    ];

    /** The bot's display name, or null when the user agent is not a known AI crawler. */
    public static function detect(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        foreach (self::BOTS as $token => [$name]) {
            if (stripos($userAgent, $token) !== false) {
                return $name;
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string, 2: string}|null [name, operator, purpose key] */
    public static function info(string $name): ?array
    {
        foreach (self::BOTS as $row) {
            if ($row[0] === $name) {
                return $row;
            }
        }

        return null;
    }
}
