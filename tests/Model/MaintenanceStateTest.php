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
        self::assertSame(['127.0.0.1', '::1'], $state->whitelistIps);
        self::assertSame(['/health'], $state->whitelistPaths);
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

    public function testWithWhitelistIpsReindexesArray(): void
    {
        $state = new MaintenanceState(whitelistIps: ['a' => '10.0.0.1', 'b' => '10.0.0.2']);
        $updated = $state->withWhitelistIps(['10.0.0.3']);

        self::assertSame(['10.0.0.3'], $updated->whitelistIps);
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

    public function testIsIpWhitelistedReturnsFalseForNullIp(): void
    {
        $state = new MaintenanceState();

        self::assertFalse($state->isIpWhitelisted(null));
    }

    public function testIsIpWhitelistedHandlesExactIp(): void
    {
        $state = new MaintenanceState(whitelistIps: ['192.168.1.42']);

        self::assertTrue($state->isIpWhitelisted('192.168.1.42'));
        self::assertFalse($state->isIpWhitelisted('192.168.1.43'));
    }

    public function testIsIpWhitelistedHandlesCidr(): void
    {
        $state = new MaintenanceState(whitelistIps: ['192.168.1.0/24']);

        self::assertTrue($state->isIpWhitelisted('192.168.1.42'));
        self::assertTrue($state->isIpWhitelisted('192.168.1.255'));
        self::assertFalse($state->isIpWhitelisted('192.168.2.1'));
    }

    public function testIsIpWhitelistedHandlesIpv6Loopback(): void
    {
        $state = new MaintenanceState(whitelistIps: ['::1']);

        self::assertTrue($state->isIpWhitelisted('::1'));
    }

    public function testIsPathWhitelistedUsesPrefixMatch(): void
    {
        $state = new MaintenanceState(whitelistPaths: ['/api', '/health']);

        self::assertTrue($state->isPathWhitelisted('/api'));
        self::assertTrue($state->isPathWhitelisted('/api/'));
        self::assertTrue($state->isPathWhitelisted('/api/users'));
        self::assertTrue($state->isPathWhitelisted('/health'));
        self::assertFalse($state->isPathWhitelisted('/admin'));
    }

    public function testIsPathWhitelistedDoesNotMatchPartialSegments(): void
    {
        $state = new MaintenanceState(whitelistPaths: ['/api']);

        // Aucun de ces chemins ne doit être whitelisté par "/api"
        self::assertFalse($state->isPathWhitelisted('/apixyz'));
        self::assertFalse($state->isPathWhitelisted('/api-v2'));
        self::assertFalse($state->isPathWhitelisted('/apiadmin'));
        self::assertFalse($state->isPathWhitelisted('/api2'));
    }

    public function testIsPathWhitelistedWithTrailingSlashInPattern(): void
    {
        $state = new MaintenanceState(whitelistPaths: ['/api/']);

        self::assertTrue($state->isPathWhitelisted('/api'));
        self::assertTrue($state->isPathWhitelisted('/api/users'));
        self::assertFalse($state->isPathWhitelisted('/apixyz'));
    }

    public function testIsPathWhitelistedRootMatchesEverything(): void
    {
        $state = new MaintenanceState(whitelistPaths: ['/']);

        self::assertTrue($state->isPathWhitelisted('/'));
        self::assertTrue($state->isPathWhitelisted('/api'));
        self::assertTrue($state->isPathWhitelisted('/anything/else'));
    }
}