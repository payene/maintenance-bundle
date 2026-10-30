<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Tests\Storage;

use Payene\MaintenanceBundle\Model\MaintenanceState;
use Payene\MaintenanceBundle\Storage\CacheStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheStorageTest extends TestCase
{
    public function testLoadReturnsDefaultStateWhenCacheEmpty(): void
    {
        $storage = new CacheStorage(new ArrayAdapter());
        $state = $storage->load();

        self::assertFalse($state->enabled);
    }

    public function testRoundTripPreservesAllFields(): void
    {
        $cache = new ArrayAdapter();
        $storage = new CacheStorage($cache);

        $original = new MaintenanceState(
            enabled: true,
            until: new \DateTimeImmutable('2026-10-29 14:00:00'),
            message: 'Cache test',
            retryAfter: 300,
            whitelistIps: ['10.0.0.1'],
            whitelistPaths: ['/api'],
        );

        $storage->save($original);
        $loaded = $storage->load();

        self::assertTrue($loaded->enabled);
        self::assertSame('Cache test', $loaded->message);
        self::assertSame(300, $loaded->retryAfter);
        self::assertSame(['10.0.0.1'], $loaded->whitelistIps);
        self::assertSame(['/api'], $loaded->whitelistPaths);
    }

    public function testLoadReturnsDefaultStateWhenCacheContainsInvalidData(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('maintenance_state');
        $item->set('not an array');
        $cache->save($item);

        $storage = new CacheStorage($cache);
        $state = $storage->load();

        self::assertFalse($state->enabled);
    }
}