<?php

namespace App\Services\Migration\Transport;

/**
 * The saved state of one package download. The address and the claim are stored ENCRYPTED with the site key (a link can
 * carry a secret in its path); how much has arrived is never stored: it is the real size of the .part file on disk.
 */
final class TransferState
{
    public const VERSION = 1;

    /**
     * @param  array{code: string, params: array<string, mixed>}|null  $error
     */
    public function __construct(
        public readonly string $id,
        public TransferStatus $status = TransferStatus::Queued,
        public string $urlEncrypted = '',
        public string $host = '',
        public string $name = '',
        public ?string $expectedSha256 = null,
        public ?int $expectedSize = null,
        public ?int $total = null,
        public ?string $claimEncrypted = null,
        public int $attempts = 0,
        public ?string $nextAttemptAt = null,
        public ?array $error = null,
        public string $message = '',
        public ?int $bytesPerSecond = null,
        public int $hashOffset = 0,
        public ?string $hashContext = null,
        public ?string $finalName = null,
        public string $initiator = 'cli',
        public string $createdAt = '',
        public string $updatedAt = '',
        public ?string $finishedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION, 'id' => $this->id, 'status' => $this->status->value,
            'url' => $this->urlEncrypted, 'host' => $this->host, 'name' => $this->name,
            'expected_sha256' => $this->expectedSha256, 'expected_size' => $this->expectedSize, 'total' => $this->total,
            'claim' => $this->claimEncrypted, 'attempts' => $this->attempts, 'next_attempt_at' => $this->nextAttemptAt,
            'error' => $this->error, 'message' => $this->message, 'bytes_per_second' => $this->bytesPerSecond,
            'hash_offset' => $this->hashOffset, 'hash_context' => $this->hashContext, 'final_name' => $this->finalName,
            'initiator' => $this->initiator, 'created_at' => $this->createdAt, 'updated_at' => $this->updatedAt,
            'finished_at' => $this->finishedAt,
        ];
    }

    /** @param  array<string, mixed>  $d */
    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) ($d['id'] ?? ''),
            status: TransferStatus::tryFrom((string) ($d['status'] ?? '')) ?? TransferStatus::Failed,
            urlEncrypted: (string) ($d['url'] ?? ''),
            host: (string) ($d['host'] ?? ''),
            name: (string) ($d['name'] ?? ''),
            expectedSha256: isset($d['expected_sha256']) ? (string) $d['expected_sha256'] : null,
            expectedSize: isset($d['expected_size']) ? (int) $d['expected_size'] : null,
            total: isset($d['total']) ? (int) $d['total'] : null,
            claimEncrypted: isset($d['claim']) ? (string) $d['claim'] : null,
            attempts: (int) ($d['attempts'] ?? 0),
            nextAttemptAt: isset($d['next_attempt_at']) ? (string) $d['next_attempt_at'] : null,
            error: isset($d['error']) && is_array($d['error']) ? $d['error'] : null,
            message: (string) ($d['message'] ?? ''),
            bytesPerSecond: isset($d['bytes_per_second']) ? (int) $d['bytes_per_second'] : null,
            hashOffset: (int) ($d['hash_offset'] ?? 0),
            hashContext: isset($d['hash_context']) ? (string) $d['hash_context'] : null,
            finalName: isset($d['final_name']) ? (string) $d['final_name'] : null,
            initiator: (string) ($d['initiator'] ?? 'cli'),
            createdAt: (string) ($d['created_at'] ?? ''),
            updatedAt: (string) ($d['updated_at'] ?? ''),
            finishedAt: isset($d['finished_at']) ? (string) $d['finished_at'] : null,
        );
    }
}
