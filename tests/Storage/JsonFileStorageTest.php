<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Tests\Storage;

use Payene\MaintenanceBundle\Model\MaintenanceState;
use Payene\MaintenanceBundle\Storage\JsonFileStorage;
use PHPUnit\Framework\TestCase;

final class JsonFileStorageTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/mud_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') as $file) {
                @unlink($file);
            }
            @rmdir($this->tmpDir);
        }
    }

    public function testLoadReturnsDefaultStateWhenFileDoesNotExist(): void
    {
        $storage = new JsonFileStorage($this->tmpDir . '/state.json');
        $state = $storage->load();

        self::assertFalse($state->enabled);
        self::assertSame(['127.0.0.1', '::1'], $state->whitelistIps);
    }

    public function testLoadReturnsDefaultStateWhenFileIsCorrupted(): void
    {
        mkdir($this->tmpDir);
        file_put_contents($this->tmpDir . '/state.json', 'not valid json {{{');

        $storage = new JsonFileStorage($this->tmpDir . '/state.json');
        $state = $storage->load();

        self::assertFalse($state->enabled);
    }

    public function testSaveCreatesFileAndDirectory(): void
    {
        $path = $this->tmpDir . '/nested/deep/state.json';
        $storage = new JsonFileStorage($path);
        $storage->save(new MaintenanceState(enabled: true));

        self::assertFileExists($path);
    }

    public function testRoundTripPreservesAllFields(): void
    {
        $path = $this->tmpDir . '/state.json';
        $storage = new JsonFileStorage($path);

        $original = new MaintenanceState(
            enabled: true,
            since: new \DateTimeImmutable('2026-10-29 12:00:00'),
            until: new \DateTimeImmutable('2026-10-29 14:00:00'),
            message: 'Test message',
            overdueMessage: 'Overdue',
            retryAfter: 600,
            whitelistIps: ['10.0.0.1', '10.0.0.2'],
            whitelistPaths: ['/api', '/health'],
        );

        $storage->save($original);
        $loaded = $storage->load();

        self::assertTrue($loaded->enabled);
        self::assertSame('2026-10-29 12:00:00', $loaded->since?->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-29 14:00:00', $loaded->until?->format('Y-m-d H:i:s'));
        self::assertSame('Test message', $loaded->message);
        self::assertSame('Overdue', $loaded->overdueMessage);
        self::assertSame(600, $loaded->retryAfter);
        self::assertSame(['10.0.0.1', '10.0.0.2'], $loaded->whitelistIps);
        self::assertSame(['/api', '/health'], $loaded->whitelistPaths);
    }

    public function testLoadToleratesMissingKeys(): void
    {
        mkdir($this->tmpDir);
        file_put_contents($this->tmpDir . '/state.json', json_encode([
            'enabled' => true,
        ]));

        $storage = new JsonFileStorage($this->tmpDir . '/state.json');
        $state = $storage->load();

        self::assertTrue($state->enabled);
        self::assertNull($state->until);
        self::assertSame(3600, $state->retryAfter);
    }

    public function testLoadToleratesInvalidDate(): void
    {
        mkdir($this->tmpDir);
        file_put_contents($this->tmpDir . '/state.json', json_encode([
            'enabled' => true,
            'until'   => 'not-a-date',
        ]));

        $storage = new JsonFileStorage($this->tmpDir . '/state.json');
        $state = $storage->load();

        self::assertTrue($state->enabled);
        self::assertNull($state->until);
    }

    public function testSaveIsIdempotent(): void
    {
        $path = $this->tmpDir . '/state.json';
        $storage = new JsonFileStorage($path);

        $state = new MaintenanceState(enabled: true, message: 'Test');
        $storage->save($state);
        $first = file_get_contents($path);

        $storage->save($state);
        $second = file_get_contents($path);

        self::assertSame($first, $second);
    }
}