<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Model;

use Symfony\Component\HttpFoundation\IpUtils;

final class MaintenanceState
{
    /**
     * @param string[] $whitelistIps
     * @param string[] $whitelistPaths
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?\DateTimeImmutable $since = null,
        public readonly ?\DateTimeImmutable $until = null,
        public readonly ?string $message = null,
        public readonly ?string $overdueMessage = null,
        public readonly int $retryAfter = 3600,
        public readonly array $whitelistIps = ['127.0.0.1', '::1'],
        public readonly array $whitelistPaths = ['/health'],
    ) {}

    public function withEnabled(bool $enabled): self
    {
        return new self(
            $enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->whitelistIps, $this->whitelistPaths,
        );
    }

    public function withSince(?\DateTimeImmutable $since): self
    {
        return new self(
            $this->enabled, $since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->whitelistIps, $this->whitelistPaths,
        );
    }

    public function withUntil(?\DateTimeImmutable $until): self
    {
        return new self(
            $this->enabled, $this->since, $until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->whitelistIps, $this->whitelistPaths,
        );
    }

    public function withMessage(?string $message): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $message,
            $this->overdueMessage, $this->retryAfter,
            $this->whitelistIps, $this->whitelistPaths,
        );
    }

    public function withOverdueMessage(?string $overdueMessage): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $overdueMessage, $this->retryAfter,
            $this->whitelistIps, $this->whitelistPaths,
        );
    }

    public function withRetryAfter(int $retryAfter): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $retryAfter,
            $this->whitelistIps, $this->whitelistPaths,
        );
    }

    /** @param string[] $whitelistIps */
    public function withWhitelistIps(array $whitelistIps): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            array_values($whitelistIps), $this->whitelistPaths,
        );
    }

    /** @param string[] $whitelistPaths */
    public function withWhitelistPaths(array $whitelistPaths): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->whitelistIps, array_values($whitelistPaths),
        );
    }

    public function isOverdue(?string $now = null): bool
    {
        if ($this->until === null) {
            return false;
        }

        $reference = $now !== null ? new \DateTimeImmutable($now) : new \DateTimeImmutable();

        return $this->until < $reference;
    }

    public function isIpWhitelisted(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        foreach ($this->whitelistIps as $allowed) {
            if (IpUtils::checkIp($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    public function isPathWhitelisted(string $path): bool
    {
        foreach ($this->whitelistPaths as $allowed) {
            // Normalise : retire les slashes finaux ("/api/" → "/api")
            $normalized = rtrim($allowed, '/');

            // Cas racine "/" : tout est whitelisté
            if ($normalized === '') {
                return true;
            }

            // Match exact : "/api" ou "/api/"
            if ($path === $normalized || $path === $normalized . '/') {
                return true;
            }

            // Match préfixe avec segment complet : "/api/users" mais PAS "/apixyz"
            if (str_starts_with($path, $normalized . '/')) {
                return true;
            }
        }

        return false;
    }
}