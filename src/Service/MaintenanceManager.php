<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Service;

use Payene\MaintenanceBundle\Model\MaintenanceState;
use Payene\MaintenanceBundle\Storage\MaintenanceStorageInterface;

final class MaintenanceManager
{
    public function __construct(
        private readonly MaintenanceStorageInterface $storage,
    ) {}

    public function getState(): MaintenanceState
    {
        return $this->storage->load();
    }

    public function isEnabled(): bool
    {
        return $this->storage->load()->enabled;
    }

    public function enable(): void
    {
        $state = $this->storage->load();

        if ($state->enabled) {
            return;
        }

        $this->storage->save(
            $state->withEnabled(true)->withSince(new \DateTimeImmutable())
        );
    }

    public function disable(): void
    {
        $state = $this->storage->load();

        if (!$state->enabled) {
            return;
        }

        $this->storage->save($state->withEnabled(false));
    }

    public function updateSettings(
        ?\DateTimeImmutable $until,
        ?string $message,
        int $retryAfter,
        ?string $overdueMessage,
    ): void {
        $state = $this->storage->load();

        $this->storage->save(
            $state
                ->withUntil($until)
                ->withMessage($message)
                ->withRetryAfter($retryAfter)
                ->withOverdueMessage($overdueMessage)
        );
    }

    public function addAllowedIp(string $ip): bool
    {
        $state = $this->storage->load();

        if (\in_array($ip, $state->allowedIps, true)) {
            return false;
        }

        $this->storage->save(
            $state->withAllowedIps([...$state->allowedIps, $ip])
        );

        return true;
    }

    public function removeAllowedIp(string $ip): bool
    {
        $state = $this->storage->load();

        if (!\in_array($ip, $state->allowedIps, true)) {
            return false;
        }

        $remaining = array_values(array_filter(
            $state->allowedIps,
            static fn (string $allowed): bool => $allowed !== $ip,
        ));

        $this->storage->save($state->withAllowedIps($remaining));

        return true;
    }

    public function addAllowedPath(string $path): bool
    {
        $state = $this->storage->load();

        if (\in_array($path, $state->allowedPaths, true)) {
            return false;
        }

        $this->storage->save(
            $state->withAllowedPaths([...$state->allowedPaths, $path])
        );

        return true;
    }

    public function removeAllowedPath(string $path): bool
    {
        $state = $this->storage->load();

        if (!\in_array($path, $state->allowedPaths, true)) {
            return false;
        }

        $remaining = array_values(array_filter(
            $state->allowedPaths,
            static fn (string $allowed): bool => $allowed !== $path,
        ));

        $this->storage->save($state->withAllowedPaths($remaining));

        return true;
    }
}