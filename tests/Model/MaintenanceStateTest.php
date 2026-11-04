<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Tests\Model;

use Payene\MaintenanceBundle\Model\MaintenanceState;
use PHPUnit\Framework\TestCase;

final class MaintenanceStateTest extends TestCase
{
    public function testDefaultStateIsDisabled(): void
    {
        $state = new MaintenanceState();

        self::assertFalse($state->enabled);
        self::assertNull($state->since);
        self::assertNull($state->until);
        self::assertNull($state->message);
        self::assertNull($state->overdueMessage);
        self::assertSame(3600, $state->retryAfter);
        self::assertSame(['127.0.0.1', '::1'], $state->allowedIps);
        self::assertSame(['/health'], $state->allowedPaths);
    }

    public function testWithEnabledReturnsNewInstance(): void
    {
        $state = new MaintenanceState();
        $enabled = $state->withEnabled(true);

        self::assertFalse($state->enabled);
        self::assertTrue($enabled->enabled);
        self::assertNotSame($state, $enabled);
    }

    public function testWithersPreserveOtherProperties(): void
    {
        $original = new MaintenanceState(
            enabled: true,
            message: 'Original',
            retryAfter: 600,
        );

        $updated = $original->withMessage('Updated');

        self::assertSame('Updated', $updated->message);
        self::assertSame('Original', $original->message);
        self::assertTrue($updated->enabled);
        self::assertSame(600, $updated->retryAfter);
    }

    public function testWithAllowedIpsReindexesArray(): void
    {
        $state = new MaintenanceState(allowedIps: ['a' => '10.0.0.1', 'b' => '10.0.0.2']);
        $updated = $state->withAllowedIps(['10.0.0.3']);

        self::assertSame(['10.0.0.3'], $updated->allowedIps);
    }

    public function testIsOverdueReturnsFalseWhenNoUntil(): void
    {
        $state = new MaintenanceState(enabled: true);

        self::assertFalse($state->isOverdue());
    }

    public function testIsOverdueReturnsFalseWhenUntilInFuture(): void
    {
        $state = new MaintenanceState(
            enabled: true,
            until: new \DateTimeImmutable('+1 hour'),
        );

        self::assertFalse($state->isOverdue());
    }

    public function testIsOverdueReturnsTrueWhenUntilInPast(): void
    {
        $state = new MaintenanceState(
            enabled: true,
            until: new \DateTimeImmutable('-1 hour'),
        );

        self::assertTrue($state->isOverdue());
    }

    public function testIsOverdueUsesProvidedReferenceTime(): void
    {
        $state = new MaintenanceState(
            enabled: true,
            until: new \DateTimeImmutable('2026-06-01 12:00:00'),
        );

        self::assertFalse($state->isOverdue('2026-06-01 11:00:00'));
        self::assertTrue($state->isOverdue('2026-06-01 13:00:00'));
    }

    public function testIsIpAllowedReturnsFalseForNullIp(): void
    {
        $state = new MaintenanceState();

        self::assertFalse($state->isIpAllowed(null));
    }

    public function testIsIpAllowedHandlesExactIp(): void
    {
        $state = new MaintenanceState(allowedIps: ['192.168.1.42']);

        self::assertTrue($state->isIpAllowed('192.168.1.42'));
        self::assertFalse($state->isIpAllowed('192.168.1.43'));
    }

    public function testIsIpAllowedHandlesCidr(): void
    {
        $state = new MaintenanceState(allowedIps: ['192.168.1.0/24']);

        self::assertTrue($state->isIpAllowed('192.168.1.42'));
        self::assertTrue($state->isIpAllowed('192.168.1.255'));
        self::assertFalse($state->isIpAllowed('192.168.2.1'));
    }

    public function testIsIpAllowedHandlesIpv6Loopback(): void
    {
        $state = new MaintenanceState(allowedIps: ['::1']);

        self::assertTrue($state->isIpAllowed('::1'));
    }

    public function testIsPathAllowedUsesPrefixMatch(): void
    {
        $state = new MaintenanceState(allowedPaths: ['/api', '/health']);

        self::assertTrue($state->isPathAllowed('/api'));
        self::assertTrue($state->isPathAllowed('/api/'));
        self::assertTrue($state->isPathAllowed('/api/users'));
        self::assertTrue($state->isPathAllowed('/health'));
        self::assertFalse($state->isPathAllowed('/admin'));
    }

    public function testIsPathAllowedDoesNotMatchPartialSegments(): void
    {
        $state = new MaintenanceState(allowedPaths: ['/api']);

        // Aucun de ces chemins ne doit être autorisé par "/api"
        self::assertFalse($state->isPathAllowed('/apixyz'));
        self::assertFalse($state->isPathAllowed('/api-v2'));
        self::assertFalse($state->isPathAllowed('/apiadmin'));
        self::assertFalse($state->isPathAllowed('/api2'));
    }

    public function testIsPathAllowedWithTrailingSlashInPattern(): void
    {
        $state = new MaintenanceState(allowedPaths: ['/api/']);

        self::assertTrue($state->isPathAllowed('/api'));
        self::assertTrue($state->isPathAllowed('/api/users'));
        self::assertFalse($state->isPathAllowed('/apixyz'));
    }

    public function testIsPathAllowedRootMatchesEverything(): void
    {
        $state = new MaintenanceState(allowedPaths: ['/']);

        self::assertTrue($state->isPathAllowed('/'));
        self::assertTrue($state->isPathAllowed('/api'));
        self::assertTrue($state->isPathAllowed('/anything/else'));
    }
}