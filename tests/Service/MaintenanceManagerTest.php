<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Tests\Service;

use Payene\MaintenanceBundle\Model\MaintenanceState;
use Payene\MaintenanceBundle\Service\MaintenanceManager;
use Payene\MaintenanceBundle\Storage\MaintenanceStorageInterface;
use PHPUnit\Framework\TestCase;

final class MaintenanceManagerTest extends TestCase
{
    public function testEnableSetsState(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        $manager->enable();

        self::assertTrue($storage->load()->enabled);
        self::assertNotNull($storage->load()->since);
    }

    public function testEnableIsIdempotent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        $manager->enable();
        $firstSince = $storage->load()->since;

        sleep(1);
        $manager->enable();
        $secondSince = $storage->load()->since;

        self::assertSame($firstSince, $secondSince);
    }

    public function testDisableSetsState(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(enabled: true, since: new \DateTimeImmutable()),
        );
        $manager = new MaintenanceManager($storage);

        $manager->disable();

        self::assertFalse($storage->load()->enabled);
    }

    public function testDisableIsIdempotent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        $manager->disable();

        self::assertFalse($storage->load()->enabled);
    }

    public function testUpdateSettingsPreservesOtherFields(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(
                enabled: true,
                allowedIps: ['10.0.0.1'],
                allowedPaths: ['/api'],
            ),
        );
        $manager = new MaintenanceManager($storage);

        $manager->updateSettings(
            new \DateTimeImmutable('2026-12-31 23:59:59'),
            'New message',
            600,
            'New overdue',
        );

        $state = $storage->load();
        self::assertSame('New message', $state->message);
        self::assertSame(600, $state->retryAfter);
        self::assertSame('New overdue', $state->overdueMessage);
        self::assertSame(['10.0.0.1'], $state->allowedIps);
        self::assertSame(['/api'], $state->allowedPaths);
    }

    public function testAddAllowedIpReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->addAllowedIp('10.0.0.5'));
        self::assertContains('10.0.0.5', $storage->load()->allowedIps);
    }

    public function testAddAllowedIpReturnsFalseIfAlreadyPresent(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(allowedIps: ['10.0.0.5']),
        );
        $manager = new MaintenanceManager($storage);

        self::assertFalse($manager->addAllowedIp('10.0.0.5'));
    }

    public function testRemoveAllowedIpReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(allowedIps: ['10.0.0.1', '10.0.0.2']),
        );
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->removeAllowedIp('10.0.0.1'));
        self::assertSame(['10.0.0.2'], $storage->load()->allowedIps);
    }

    public function testRemoveAllowedIpReturnsFalseIfNotPresent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertFalse($manager->removeAllowedIp('10.0.0.99'));
    }

    public function testRemoveAllowedIpReindexesArray(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(allowedIps: ['10.0.0.1', '10.0.0.2', '10.0.0.3']),
        );
        $manager = new MaintenanceManager($storage);

        $manager->removeAllowedIp('10.0.0.2');

        self::assertSame(['10.0.0.1', '10.0.0.3'], $storage->load()->allowedIps);
    }

    public function testAddAllowedPathReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->addAllowedPath('/api'));
        self::assertContains('/api', $storage->load()->allowedPaths);
    }

    public function testAddAllowedPathReturnsFalseIfAlreadyPresent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        $manager->addAllowedPath('/api');
        self::assertFalse($manager->addAllowedPath('/api'));
    }

    public function testRemoveAllowedPathReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(allowedPaths: ['/api', '/health']),
        );
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->removeAllowedPath('/api'));
        self::assertSame(['/health'], $storage->load()->allowedPaths);
    }

    public function testRemoveAllowedPathReturnsFalseIfNotPresent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertFalse($manager->removeAllowedPath('/not-found'));
    }

    private function createInMemoryStorage(?MaintenanceState $initial = null): MaintenanceStorageInterface
    {
        return new class($initial ?? new MaintenanceState()) implements MaintenanceStorageInterface {
            private MaintenanceState $state;

            public function __construct(MaintenanceState $initial)
            {
                $this->state = $initial;
            }

            public function load(): MaintenanceState
            {
                return $this->state;
            }

            public function save(MaintenanceState $state): void
            {
                $this->state = $state;
            }
        };
    }
}