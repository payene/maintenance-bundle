<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Model;

use Symfony\Component\HttpFoundation\IpUtils;

final class MaintenanceState
{
    /**
     * @param string[] $allowedIps
     * @param string[] $allowedPaths
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?\DateTimeImmutable $since = null,
        public readonly ?\DateTimeImmutable $until = null,
        public readonly ?string $message = null,
        public readonly ?string $overdueMessage = null,
        public readonly int $retryAfter = 3600,
        public readonly array $allowedIps = ['127.0.0.1', '::1'],
        public readonly array $allowedPaths = ['/health'],
    ) {}

    public function withEnabled(bool $enabled): self
    {
        return new self(
            $enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->allowedIps, $this->allowedPaths,
        );
    }

    public function withSince(?\DateTimeImmutable $since): self
    {
        return new self(
            $this->enabled, $since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->allowedIps, $this->allowedPaths,
        );
    }

    public function withUntil(?\DateTimeImmutable $until): self
    {
        return new self(
            $this->enabled, $this->since, $until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->allowedIps, $this->allowedPaths,
        );
    }

    public function withMessage(?string $message): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $message,
            $this->overdueMessage, $this->retryAfter,
            $this->allowedIps, $this->allowedPaths,
        );
    }

    public function withOverdueMessage(?string $overdueMessage): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $overdueMessage, $this->retryAfter,
            $this->allowedIps, $this->allowedPaths,
        );
    }

    public function withRetryAfter(int $retryAfter): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $retryAfter,
            $this->allowedIps, $this->allowedPaths,
        );
    }

    /** @param string[] $allowedIps */
    public function withAllowedIps(array $allowedIps): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            array_values($allowedIps), $this->allowedPaths,
        );
    }

    /** @param string[] $allowedPaths */
    public function withAllowedPaths(array $allowedPaths): self
    {
        return new self(
            $this->enabled, $this->since, $this->until, $this->message,
            $this->overdueMessage, $this->retryAfter,
            $this->allowedIps, array_values($allowedPaths),
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

    public function isIpAllowed(?string $ip): bool
    {
        if ($ip === null) {
            return false;
        }

        foreach ($this->allowedIps as $allowed) {
            if (IpUtils::checkIp($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    public function isPathAllowed(string $path): bool
    {
        foreach ($this->allowedPaths as $allowed) {
            // Normalize: remove trailing slashes ("/api/" -> "/api")
            $normalized = rtrim($allowed, '/');

            // Root case "/": everything is allowed
            if ($normalized === '') {
                return true;
            }

            // Exact match: "/api" or "/api/"
            if ($path === $normalized || $path === $normalized . '/') {
                return true;
            }

            // Prefix match with full segment: "/api/users" but NOT "/apixyz"
            if (str_starts_with($path, $normalized . '/')) {
                return true;
            }
        }

        return false;
    }
}