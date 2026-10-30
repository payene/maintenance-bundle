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
            throw new \RuntimeException(sprintf('Impossible de créer le dossier "%s".', $dir));
        }

        $payload = json_encode(
            $this->dehydrate($state),
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        );

        if ($payload === false) {
            throw new \RuntimeException('Échec de l\'encodage JSON de l\'état de maintenance.');
        }

        if (file_put_contents($this->path, $payload, \LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Impossible d\'écrire le fichier "%s".', $this->path));
        }
    }

    private function hydrate(array $data): MaintenanceState
    {
        return new MaintenanceState(
            enabled:        (bool) ($data['enabled'] ?? false),
            since:          $this->parseDate($data['since']           ?? null),
            until:          $this->parseDate($data['until']           ?? null),
            message:        isset($data['message'])         ? (string) $data['message']         : null,
            overdueMessage: isset($data['overdue_message']) ? (string) $data['overdue_message'] : null,
            retryAfter:     (int) ($data['retry_after'] ?? 3600),
            whitelistIps:   isset($data['whitelist_ips'])   && \is_array($data['whitelist_ips'])
                ? array_values(array_map('strval', $data['whitelist_ips']))
                : ['127.0.0.1', '::1'],
            whitelistPaths: isset($data['whitelist_paths']) && \is_array($data['whitelist_paths'])
                ? array_values(array_map('strval', $data['whitelist_paths']))
                : ['/health'],
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
            'whitelist_ips'   => $state->whitelistIps,
            'whitelist_paths' => $state->whitelistPaths,
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