<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Storage;

use Payene\MaintenanceBundle\Model\MaintenanceState;

final class JsonFileStorage implements MaintenanceStorageInterface
{
    public function __construct(
        private readonly string $path,
    ) {}

    public function load(): MaintenanceState
    {
        if (!is_file($this->path)) {
            return new MaintenanceState();
        }

        $raw = @file_get_contents($this->path);
        if ($raw === false) {
            return new MaintenanceState();
        }

        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            return new MaintenanceState();
        }

        return $this->hydrate($data);
    }

    public function save(MaintenanceState $state): void
    {
        $dir = \dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $dir));
        }

        $payload = json_encode(
            $this->dehydrate($state),
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        );

        if ($payload === false) {
            throw new \RuntimeException('Failed to encode maintenance state to JSON.');
        }

        if (file_put_contents($this->path, $payload, \LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write file "%s".', $this->path));
        }
    }

    private function hydrate(array $data): MaintenanceState
    {
        // Backward compatibility: fallback to 'whitelist_ips' for v1.0.0 files
        $allowedIps = $data['allowed_ips'] ?? $data['whitelist_ips'] ?? ['127.0.0.1', '::1'];
        $allowedPaths = $data['allowed_paths'] ?? $data['whitelist_paths'] ?? ['/health'];

        return new MaintenanceState(
            enabled:        (bool) ($data['enabled'] ?? false),
            since:          $this->parseDate($data['since']           ?? null),
            until:          $this->parseDate($data['until']           ?? null),
            message:        isset($data['message'])         ? (string) $data['message']         : null,
            overdueMessage: isset($data['overdue_message']) ? (string) $data['overdue_message'] : null,
            retryAfter:     (int) ($data['retry_after'] ?? 3600),
            allowedIps:     is_array($allowedIps) ? array_values(array_map('strval', $allowedIps)) : ['127.0.0.1', '::1'],
            allowedPaths:   is_array($allowedPaths) ? array_values(array_map('strval', $allowedPaths)) : ['/health'],
        );
    }

    private function dehydrate(MaintenanceState $state): array
    {
        return [
            'enabled'         => $state->enabled,
            'since'           => $state->since?->format(\DATE_ATOM),
            'until'           => $state->until?->format(\DATE_ATOM),
            'message'         => $state->message,
            'overdue_message' => $state->overdueMessage,
            'retry_after'     => $state->retryAfter,
            'allowed_ips'     => $state->allowedIps,
            'allowed_paths'   => $state->allowedPaths,
        ];
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}