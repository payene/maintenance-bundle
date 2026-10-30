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
                whitelistIps: ['10.0.0.1'],
                whitelistPaths: ['/api'],
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
        self::assertSame(['10.0.0.1'], $state->whitelistIps);
        self::assertSame(['/api'], $state->whitelistPaths);
    }

    public function testAddWhitelistIpReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->addWhitelistIp('10.0.0.5'));
        self::assertContains('10.0.0.5', $storage->load()->whitelistIps);
    }

    public function testAddWhitelistIpReturnsFalseIfAlreadyPresent(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(whitelistIps: ['10.0.0.5']),
        );
        $manager = new MaintenanceManager($storage);

        self::assertFalse($manager->addWhitelistIp('10.0.0.5'));
    }

    public function testRemoveWhitelistIpReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(whitelistIps: ['10.0.0.1', '10.0.0.2']),
        );
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->removeWhitelistIp('10.0.0.1'));
        self::assertSame(['10.0.0.2'], $storage->load()->whitelistIps);
    }

    public function testRemoveWhitelistIpReturnsFalseIfNotPresent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertFalse($manager->removeWhitelistIp('10.0.0.99'));
    }

    public function testRemoveWhitelistIpReindexesArray(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(whitelistIps: ['10.0.0.1', '10.0.0.2', '10.0.0.3']),
        );
        $manager = new MaintenanceManager($storage);

        $manager->removeWhitelistIp('10.0.0.2');

        self::assertSame(['10.0.0.1', '10.0.0.3'], $storage->load()->whitelistIps);
    }

    public function testAddWhitelistPathReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->addWhitelistPath('/api'));
        self::assertContains('/api', $storage->load()->whitelistPaths);
    }

    public function testAddWhitelistPathReturnsFalseIfAlreadyPresent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        $manager->addWhitelistPath('/api');
        self::assertFalse($manager->addWhitelistPath('/api'));
    }

    public function testRemoveWhitelistPathReturnsTrueOnSuccess(): void
    {
        $storage = $this->createInMemoryStorage(
            new MaintenanceState(whitelistPaths: ['/api', '/health']),
        );
        $manager = new MaintenanceManager($storage);

        self::assertTrue($manager->removeWhitelistPath('/api'));
        self::assertSame(['/health'], $storage->load()->whitelistPaths);
    }

    public function testRemoveWhitelistPathReturnsFalseIfNotPresent(): void
    {
        $storage = $this->createInMemoryStorage();
        $manager = new MaintenanceManager($storage);

        self::assertFalse($manager->removeWhitelistPath('/not-found'));
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